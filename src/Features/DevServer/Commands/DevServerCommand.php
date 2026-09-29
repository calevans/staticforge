<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\DevServer\Commands;

use EICC\StaticForge\Features\DevServer\Services\BuildRequest;
use EICC\StaticForge\Features\DevServer\Services\BuildRunnerInterface;
use EICC\StaticForge\Features\DevServer\Services\ClockInterface;
use EICC\StaticForge\Features\DevServer\Services\ErrorSanitizer;
use EICC\StaticForge\Features\DevServer\Services\FileSignatureProvider;
use EICC\StaticForge\Features\DevServer\Services\HostDecision;
use EICC\StaticForge\Features\DevServer\Services\HostPolicy;
use EICC\StaticForge\Features\DevServer\Services\PrivateStateDir;
use EICC\StaticForge\Features\DevServer\Services\ProcessBuildRunner;
use EICC\StaticForge\Features\DevServer\Services\SystemClock;
use EICC\StaticForge\Features\DevServer\Services\WatchLoop;
use EICC\Utils\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'site:devserver',
    description: 'Start development server with proper 404 handling'
)]
class DevServerCommand extends Command implements SignalableCommandInterface
{
    private const MAX_LINE_BYTES = 8192;

    private ?PrivateStateDir $stateDir = null;
    private string $publicDir;
    private ClockInterface $clock;
    private ?SymfonyStyle $io = null;

    /** @var resource|null */
    private $serverProcess = null;
    /** @var array<int, resource> */
    private array $serverPipes = [];

    private int $stateVersion = 1;

    public function __construct(
        private readonly Container $container,
        private ?BuildRunnerInterface $runner = null,
        ?ClockInterface $clock = null
    ) {
        parent::__construct();
        $this->clock = $clock ?? new SystemClock();
    }

    protected function configure(): void
    {
        $this
            ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'Port to serve on', '8000')
            ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'Host to bind to', 'localhost')
            ->addOption('watch', null, InputOption::VALUE_NONE, 'Rebuild on source changes and reload the browser')
            ->addOption(
                'allow-remote',
                null,
                InputOption::VALUE_NONE,
                'With --watch, allow binding a non-loopback host (site and build errors reachable from the network)'
            )
            ->addOption('include-drafts', null, InputOption::VALUE_NONE, 'With --watch, include drafts in rebuilds')
            ->setHelp('This command starts a development server with proper 404 handling for static files.');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $output->writeln("CWD: " . getcwd());
        $outputDir = $this->container->hasVariable('OUTPUT_DIR')
            ? $this->container->getVariable('OUTPUT_DIR')
            : null;
        $this->publicDir = $outputDir ?: (getcwd() . '/public');

        register_shutdown_function([$this, 'cleanup']);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->io = new SymfonyStyle($input, $output);

        $host = (string) $input->getOption('host');
        $port = (int) $input->getOption('port');
        $watch = (bool) $input->getOption('watch');

        // Check if public directory exists
        if (!is_dir($this->publicDir)) {
            $io->error("Public directory not found: {$this->publicDir}");
            $io->note('Run "php bin/staticforge.php site:render" first to generate the site.');
            return Command::FAILURE;
        }

        if ($watch && !$this->checkWatchPreconditions($io, $host, (bool) $input->getOption('allow-remote'))) {
            return Command::FAILURE;
        }

        // Check if port is available
        if ($this->isPortInUse($host, $port)) {
            $io->error("Port {$port} is already in use on {$host}");
            return Command::FAILURE;
        }

        try {
            $stateDir = $this->stateDir = PrivateStateDir::create();
            $stateFile = $stateDir->file('state.json');
            if ($watch) {
                $this->writeState('ok', '');
            }
            $stateDir->write(
                'router.php',
                $this->buildRouterSource($host, $watch, $stateFile, (bool) $input->getOption('allow-remote'))
            );

            $io->success("Development server starting...");
            $io->info("Server: http://{$host}:{$port}");
            $io->info("Document root: {$this->publicDir}");
            $io->warning("Press Ctrl+C to stop the server");
            $io->newLine();

            $this->startServer($host, $port);
            $stopped = $this->runLoop($io, $watch, (bool) $input->getOption('include-drafts'), $port);
        } catch (\Exception $e) {
            $io->error("Failed to start server: " . $e->getMessage());
            $this->cleanup();
            return Command::FAILURE;
        }

        $this->cleanup();

        return $stopped ? Command::SUCCESS : Command::FAILURE;
    }

    private function checkWatchPreconditions(SymfonyStyle $io, string $host, bool $allowRemote): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $io->error('--watch is not supported on native Windows. Use WSL2 or Lando.');
            return false;
        }

        $conflict = $this->outputDirConflict();
        if ($conflict !== null) {
            $io->error($conflict);
            return false;
        }

        switch (HostPolicy::decide($host, $allowRemote, getenv('LANDO') === 'ON')) {
            case HostDecision::Refuse:
                $io->error(
                    "Refusing to bind {$host} with --watch: it is reachable from the network. "
                    . 'Use --allow-remote if that is intended.'
                );
                return false;
            case HostDecision::WarnLando:
                $io->warning("Binding {$host} under Lando: the site and build errors are reachable from the "
                    . 'container network.');
                break;
            case HostDecision::WarnRemote:
                $io->warning("--allow-remote: the site and build errors are reachable from the network via {$host}.");
                break;
            case HostDecision::Run:
                break;
        }

        return true;
    }

    private function outputDirConflict(): ?string
    {
        $output = realpath($this->publicDir);
        if ($output === false) {
            return null;
        }

        foreach (['SOURCE_DIR', 'TEMPLATE_DIR'] as $name) {
            $value = $this->container->hasVariable($name) ? $this->container->getVariable($name) : null;
            $dir = is_string($value) && $value !== '' ? realpath($value) : false;
            if ($dir === false) {
                continue;
            }
            if (
                $output === $dir
                || str_starts_with($output, $dir . '/')
                || str_starts_with($dir, $output . '/')
            ) {
                return "Cannot use --watch: OUTPUT_DIR ({$output}) is inside, equal to or a parent of "
                    . "{$name} ({$dir}); "
                    . 'every build would retrigger itself.';
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function watchedRoots(): array
    {
        $appRoot = rtrim((string) $this->container->getVariable('app_root'), '/');
        $roots = [$appRoot . '/siteconfig.yaml', $appRoot . '/siteconfig.d', $appRoot . '/.env'];
        foreach (['SOURCE_DIR', 'TEMPLATE_DIR'] as $name) {
            $value = $this->container->hasVariable($name) ? $this->container->getVariable($name) : null;
            if (is_string($value) && $value !== '') {
                $roots[] = $value;
            }
        }

        return $roots;
    }

    public function buildRouterSource(string $host, bool $watch, string $stateFile, bool $allowRemote = false): string
    {
        $loader = (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName();
        $autoload = dirname((string) $loader) . '/../autoload.php';
        $allowed = HostPolicy::allowedHosts(
            $host,
            getenv('LANDO') === 'ON',
            getenv('LANDO_INFO') !== false ? (string) getenv('LANDO_INFO') : null
        );
        $appRoot = $this->container->hasVariable('app_root') ? (string) $this->container->getVariable('app_root') : '';

        return "<?php\n\ndeclare(strict_types=1);\n\n"
            . '// Generated by site:devserver; removed on shutdown. Do not edit.' . "\n"
            . 'require_once ' . var_export($autoload, true) . ";\n\n"
            . '$router = new \\EICC\\StaticForge\\Features\\DevServer\\Services\\DevServerRouter('
            . var_export($this->publicDir, true) . ', '
            . var_export($watch, true) . ', '
            . var_export($stateFile, true) . ', '
            . var_export($allowed, true) . ', '
            . var_export($appRoot, true) . ', '
            . var_export($allowRemote, true) . ");\n\n"
            . "return \$router->dispatch(\$_SERVER);\n";
    }

    private function startServer(string $host, int $port): void
    {
        if ($this->stateDir === null) {
            throw new \RuntimeException('Private state directory missing');
        }

        $bind = str_contains($host, ':') && !str_starts_with($host, '[') ? "[{$host}]" : $host;
        $argv = [PHP_BINARY, '-S', "{$bind}:{$port}", '-t', $this->publicDir, $this->stateDir->file('router.php')];

        $process = proc_open(
            $argv,
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->publicDir,
            $this->serverEnvironment()
        );
        if (!is_resource($process)) {
            throw new \RuntimeException("Failed to start PHP server");
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $this->serverProcess = $process;
        $this->serverPipes = [1 => $pipes[1], 2 => $pipes[2]];
    }

    /**
     * The server child serves files and runs the router; it never needs the
     * secrets (SFTP, keys) that live in the parent's environment.
     *
     * @return array<string, string>
     */
    private function serverEnvironment(): array
    {
        $env = [];
        foreach (getenv() as $name => $value) {
            $name = (string) $name;
            if (
                in_array($name, ['PATH', 'HOME', 'TMPDIR', 'LANG', 'TZ'], true)
                || str_starts_with($name, 'LC_')
            ) {
                $env[$name] = (string) $value;
            }
        }

        return $env;
    }

    /**
     * @return bool true when the server was stopped on purpose, false when it exited by itself
     */
    private function runLoop(SymfonyStyle $io, bool $watch, bool $includeDrafts, int $port): bool
    {
        $appRoot = rtrim((string) $this->container->getVariable('app_root'), '/');
        $buffers = [1 => '', 2 => ''];
        $provider = null;
        $runner = null;
        $loop = null;
        $nextTick = 0;
        $current = null;
        $startedAt = 0;

        if ($watch) {
            $runner = $this->runner ??= new ProcessBuildRunner($appRoot, $includeDrafts, "http://localhost:{$port}/");
            $provider = new FileSignatureProvider(
                $this->watchedRoots(),
                array_values(array_filter([$this->publicDir, $this->stateDir?->path()]))
            );
            $loop = new WatchLoop($this->clock);
            $io->text('Watching for changes...');
        }

        while ($this->serverProcess !== null && proc_get_status($this->serverProcess)['running']) {
            $read = array_values($this->serverPipes);
            if ($read === []) {
                usleep(100000);
            } else {
                $write = null;
                $except = null;
                if (@stream_select($read, $write, $except, 0, 100000) > 0) {
                    foreach ($this->serverPipes as $id => $pipe) {
                        if (in_array($pipe, $read, true)) {
                            $this->pump($io, $id, $buffers, $watch);
                        }
                    }
                }
            }

            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
            if ($this->serverProcess === null) {
                return true;
            }

            if ($watch && $provider !== null && $loop !== null && $runner !== null) {
                // Checked every iteration (not just per scan) so the build pipes are drained often.
                if ($current !== null && !$runner->isRunning()) {
                    $ok = $this->finishBuild($io, $runner, $current, $startedAt, $appRoot);
                    $loop->buildFinished($ok);
                    $current = null;
                }

                $now = $this->clock->nowMs();
                if ($now < $nextTick) {
                    continue;
                }

                $signature = $provider->signature();
                $nextTick = $this->clock->nowMs() + $provider->intervalMs();

                $request = $loop->tick($signature, $runner->isRunning());
                if ($request !== null) {
                    $current = $request;
                    $startedAt = $this->clock->nowMs();
                    $this->writeState('building', '');
                    $runner->start($request->clean);
                }
            }
        }

        if ($this->serverProcess === null) {
            return true;
        }

        $this->drainServerPipes($io, $buffers, $watch);
        foreach ($buffers as $id => $rest) {
            $this->printLine($io, $rest, $watch);
            $buffers[$id] = '';
        }
        $io->error('The development server exited unexpectedly.');

        return false;
    }

    /**
     * @param array<int, string> $buffers
     */
    private function drainServerPipes(SymfonyStyle $io, array &$buffers, bool $watch): void
    {
        foreach (array_keys($this->serverPipes) as $id) {
            for ($i = 0; $i < 64 && isset($this->serverPipes[$id]); $i++) {
                $chunk = fread($this->serverPipes[$id], 8192);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $this->consume($io, $id, $chunk, $buffers, $watch);
            }
        }
    }

    /**
     * @param array<int, string> $buffers
     */
    private function pump(SymfonyStyle $io, int $id, array &$buffers, bool $watch): void
    {
        $chunk = fread($this->serverPipes[$id], 8192);
        if ($chunk === false || $chunk === '') {
            if (feof($this->serverPipes[$id])) {
                fclose($this->serverPipes[$id]);
                unset($this->serverPipes[$id]);
            }
            return;
        }

        $this->consume($io, $id, $chunk, $buffers, $watch);
    }

    /**
     * @param array<int, string> $buffers
     */
    private function consume(SymfonyStyle $io, int $id, string $chunk, array &$buffers, bool $watch): void
    {
        $buffers[$id] .= $chunk;
        while (($pos = strpos($buffers[$id], "\n")) !== false) {
            $line = substr($buffers[$id], 0, $pos);
            $buffers[$id] = substr($buffers[$id], $pos + 1);
            $this->printLine($io, $line, $watch);
        }
        if (strlen($buffers[$id]) > self::MAX_LINE_BYTES) {
            $this->printLine($io, $buffers[$id], $watch);
            $buffers[$id] = '';
        }
    }

    private function printLine(SymfonyStyle $io, string $line, bool $watch): void
    {
        $line = trim((string) preg_replace('/[\x00-\x08\x0b-\x1f\x7f]/', '', mb_scrub($line)));
        if ($line !== '' && !$this->isPollNoise($line, $watch)) {
            $io->text(OutputFormatter::escape(mb_substr($line, 0, self::MAX_LINE_BYTES)));
        }
    }

    private function isPollNoise(string $line, bool $watch): bool
    {
        if (str_contains($line, '/__staticforge/')) {
            return true;
        }

        // The built-in server logs the connection lines without a path, so the
        // once-a-second reload poll would otherwise flood the terminal.
        return $watch && preg_match('/ (Accepted|Closing)$/', $line) === 1;
    }

    private function finishBuild(
        SymfonyStyle $io,
        BuildRunnerInterface $runner,
        BuildRequest $request,
        int $startedAt,
        string $appRoot
    ): bool {
        $ok = $runner->exitCode() === 0;
        $error = '';
        $seconds = number_format(($this->clock->nowMs() - $startedAt) / 1000, 1);
        $first = $this->relative($request->firstPath(), $appRoot);
        $total = count($request->changed) + count($request->deleted);
        $more = $total > 1 ? ' (+' . ($total - 1) . ' more)' : '';
        $why = $this->fullRebuildReasons($request, $appRoot);

        if ($ok) {
            $this->stateVersion++;
            $this->writeState('ok', '');
        } else {
            $error = $this->extractError($runner->output(), $appRoot);
            $this->writeState('failed', $error);
        }

        $io->text(sprintf(
            '[%s] %s %ss %s%s%s',
            date('H:i:s'),
            $ok ? 'OK' : 'FAILED',
            $seconds,
            $first,
            $more,
            $why === '' ? '' : ' [' . $why . ']'
        ));
        if ($error !== '') {
            $io->text(OutputFormatter::escape($error));
        }

        return $ok;
    }

    private function fullRebuildReasons(BuildRequest $request, string $appRoot): string
    {
        $reasons = [];
        if ($request->deleted !== []) {
            $reasons[] = 'source deleted/renamed: --clean';
        }

        $templateDir = $this->container->hasVariable('TEMPLATE_DIR')
            ? (string) realpath((string) $this->container->getVariable('TEMPLATE_DIR'))
            : '';
        foreach ([...$request->changed, ...$request->deleted] as $path) {
            $rel = $this->relative($path, $appRoot);
            if ($rel === '.env' || str_starts_with($rel, 'siteconfig')) {
                $reasons['config'] = 'config changed: full re-render';
            } elseif ($templateDir !== '' && str_starts_with($path, $templateDir . '/')) {
                $reasons['templates'] = 'templates changed: full re-render';
            }
        }

        return implode(', ', $reasons);
    }

    private function relative(string $path, string $appRoot): string
    {
        return str_starts_with($path, $appRoot . '/') ? substr($path, strlen($appRoot) + 1) : $path;
    }

    private function extractError(string $output, string $appRoot): string
    {
        $output = (string) preg_replace('/\e\[[0-9;]*m/', '', mb_scrub($output));
        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\R/', $output) ?: []),
            static fn(string $line): bool => $line !== ''
        ));
        $errors = array_values(array_filter(
            $lines,
            static fn(string $line): bool => preg_match('/error|exception|fail/i', $line) === 1
        ));

        $picked = array_slice($errors !== [] ? $errors : array_slice($lines, -5), 0, 5);

        return ErrorSanitizer::sanitize(implode("\n", $picked), $appRoot);
    }

    private function writeState(string $status, string $error): void
    {
        try {
            $json = json_encode(
                ['v' => $this->stateVersion, 'status' => $status, 'error' => mb_scrub($error)],
                JSON_INVALID_UTF8_SUBSTITUTE
            );
            if ($json === false) {
                // Keep the previous state file rather than resetting the version to 0.
                throw new \RuntimeException('Could not encode the watch state: ' . json_last_error_msg());
            }
            $this->stateDir?->write('state.json', $json);
        } catch (\Throwable $e) {
            $this->io?->warning('Could not update the reload state: ' . OutputFormatter::escape($e->getMessage()));
        }
    }

    private function isPortInUse(string $host, int $port): bool
    {
        $socket = @fsockopen($host, $port, $errno, $errstr, 1);
        if ($socket) {
            fclose($socket);
            return true;
        }
        return false;
    }

    /**
     * @return array<int, int>
     */
    public function getSubscribedSignals(): array
    {
        // ext-pcntl is optional (it is not in composer.json's require), and the
        // SIG* constants only exist when it is loaded.
        if (!\function_exists('pcntl_signal')) {
            return [];
        }

        return [\SIGINT, \SIGTERM, \SIGHUP, \SIGQUIT];
    }

    /**
     * Registering this method with pcntl_signal() directly used to make every
     * Ctrl+C fatal: pcntl invokes a handler as (int $signo, array|null $siginfo),
     * but this signature is inherited from Command and declares int|false as its
     * second parameter, so the siginfo array hit a TypeError under strict_types.
     * Symfony's SignalRegistry owns the pcntl callback instead and calls this
     * with the contract it is actually typed for.
     */
    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        echo "\nShutting down development server...\n";
        $this->cleanup();

        return 0;
    }

    public function cleanup(): void
    {
        $this->runner?->stop();

        if ($this->serverProcess !== null) {
            proc_terminate($this->serverProcess);
            $deadline = microtime(true) + 2.0;
            while (microtime(true) < $deadline && proc_get_status($this->serverProcess)['running']) {
                usleep(50000);
            }
            if (proc_get_status($this->serverProcess)['running']) {
                proc_terminate($this->serverProcess, 9);
            }
            foreach ($this->serverPipes as $pipe) {
                fclose($pipe);
            }
            proc_close($this->serverProcess);
            $this->serverProcess = null;
            $this->serverPipes = [];
        }

        $this->stateDir?->remove();
        $this->stateDir = null;
    }
}
