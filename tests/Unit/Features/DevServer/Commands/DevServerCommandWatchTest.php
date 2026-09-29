<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\DevServer\Commands;

use EICC\StaticForge\Features\DevServer\Commands\DevServerCommand;
use EICC\StaticForge\Features\DevServer\Services\BuildRequest;
use EICC\StaticForge\Features\DevServer\Services\PrivateStateDir;
use EICC\StaticForge\Tests\Mocks\FakeBuildRunner;
use EICC\StaticForge\Tests\Mocks\FakeClock;
use EICC\StaticForge\Tests\Unit\Features\DevServer\Services\SymlinkSafeCleanup;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Watch-mode logic of DevServerCommand that can run without starting a server:
 * startup refusals, host policy wiring, build-result bookkeeping, router generation.
 * The live loop is covered by tests/Integration/DevServer/DevServerWatchIntegrationTest.php.
 */
class DevServerCommandWatchTest extends UnitTestCase
{
    use SymlinkSafeCleanup;

    private string $base;
    private ?string $originalLando;
    private ?string $originalLandoInfo;
    /** @var list<PrivateStateDir> */
    private array $dirs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = (string) realpath(sys_get_temp_dir()) . '/sf_watchcmd_' . bin2hex(random_bytes(4));
        foreach (['public', 'content', 'templates', 'app'] as $name) {
            mkdir($this->base . '/' . $name, 0755, true);
        }
        $lando = getenv('LANDO');
        $info = getenv('LANDO_INFO');
        $this->originalLando = $lando === false ? null : $lando;
        $this->originalLandoInfo = $info === false ? null : $info;
        putenv('LANDO');
        putenv('LANDO_INFO');

        $this->setContainerVariable('app_root', $this->base . '/app');
        $this->setContainerVariable('OUTPUT_DIR', $this->base . '/public');
        $this->setContainerVariable('SOURCE_DIR', $this->base . '/content');
        $this->setContainerVariable('TEMPLATE_DIR', $this->base . '/templates');
    }

    protected function tearDown(): void
    {
        foreach ($this->dirs as $dir) {
            $dir->remove();
        }
        putenv($this->originalLando === null ? 'LANDO' : 'LANDO=' . $this->originalLando);
        putenv($this->originalLandoInfo === null ? 'LANDO_INFO' : 'LANDO_INFO=' . $this->originalLandoInfo);
        $this->removeDirectory($this->base);
        parent::tearDown();
    }

    private function command(?FakeBuildRunner $runner = null, ?FakeClock $clock = null): DevServerCommand
    {
        $command = new DevServerCommand($this->container, $runner, $clock);
        (new ReflectionProperty($command, 'publicDir'))->setValue($command, $this->base . '/public');

        return $command;
    }

    /**
     * @return list<string>
     */
    private function stateDirs(): array
    {
        return glob(sys_get_temp_dir() . '/' . PrivateStateDir::PREFIX . '*') ?: [];
    }

    private function runWatch(string $host = 'localhost', bool $allowRemote = false): CommandTester
    {
        $application = new Application();
        $application->addCommand($this->command());
        $tester = new CommandTester($application->find('site:devserver'));
        $tester->execute(['--watch' => true, '--host' => $host, '--allow-remote' => $allowRemote, '--port' => '1']);

        return $tester;
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function conflictProvider(): array
    {
        return [
            'output equals source' => ['content', 'public-is-content', 'SOURCE_DIR'],
            'output inside source' => ['content/out', 'public-inside-content', 'SOURCE_DIR'],
            'output parent of source' => ['', 'public-is-base', 'SOURCE_DIR'],
            'output equals templates' => ['templates', 'public-is-templates', 'TEMPLATE_DIR'],
            'output inside templates' => ['templates/out', 'public-inside-templates', 'TEMPLATE_DIR'],
        ];
    }

    #[DataProvider('conflictProvider')]
    public function testWatchRefusesWhenOutputDirOverlapsSourceOrTemplates(
        string $outputRelative,
        string $label,
        string $variable
    ): void {
        $output = rtrim($this->base . '/' . $outputRelative, '/');
        if (!is_dir($output)) {
            mkdir($output, 0755, true);
        }
        $this->setContainerVariable('OUTPUT_DIR', $output);
        $application = new Application();
        $command = new DevServerCommand($this->container);
        $application->addCommand($command);
        $before = $this->stateDirs();

        $tester = new CommandTester($application->find('site:devserver'));
        $tester->execute(['--watch' => true, '--port' => '1']);

        $this->assertSame(1, $tester->getStatusCode(), $label);
        $display = preg_replace('/\s+/', ' ', $tester->getDisplay());
        $this->assertStringContainsString('Cannot use --watch', (string) $display, $label);
        $this->assertStringContainsString($variable, (string) $display, $label);
        $this->assertSame($before, $this->stateDirs(), 'refusal must not create a private dir');
    }

    public function testWatchRefusesWhenOutputDirIsParentOfTemplatesEvenIfSourceIsElsewhere(): void
    {
        mkdir($this->base . '/site/tpl', 0755, true);
        mkdir($this->base . '/elsewhere', 0755);
        $this->setContainerVariable('OUTPUT_DIR', $this->base . '/site');
        $this->setContainerVariable('TEMPLATE_DIR', $this->base . '/site/tpl');
        $this->setContainerVariable('SOURCE_DIR', $this->base . '/elsewhere');

        $tester = $this->runWatchWithPublic($this->base . '/site');

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('TEMPLATE_DIR', preg_replace('/\s+/', ' ', $tester->getDisplay()) ?? '');
    }

    private function runWatchWithPublic(string $public): CommandTester
    {
        $application = new Application();
        $application->addCommand(new DevServerCommand($this->container));
        $tester = new CommandTester($application->find('site:devserver'));
        $tester->execute(['--watch' => true, '--port' => '1']);
        $this->assertDirectoryExists($public);

        return $tester;
    }

    public function testSiblingDirectoriesWithSharedNamePrefixAreNotAConflict(): void
    {
        mkdir($this->base . '/content-out', 0755);
        $this->setContainerVariable('OUTPUT_DIR', $this->base . '/content-out');
        $command = $this->command();
        (new ReflectionProperty($command, 'publicDir'))->setValue($command, $this->base . '/content-out');

        $this->assertNull((new ReflectionMethod($command, 'outputDirConflict'))->invoke($command));
    }

    public function testDistinctDirectoriesPassThePreconditionsOnLoopback(): void
    {
        $output = new BufferedOutput();
        $io = new SymfonyStyle(new \Symfony\Component\Console\Input\ArrayInput([]), $output);

        $ok = (new ReflectionMethod($this->command(), 'checkWatchPreconditions'))
            ->invoke($this->command(), $io, '127.0.0.1', false);

        $this->assertTrue($ok);
        $this->assertSame('', trim($output->fetch()));
    }

    public function testNonLoopbackHostWithoutAllowRemoteIsRefusedOutsideLando(): void
    {
        $before = $this->stateDirs();

        $tester = $this->runWatch('192.168.1.10');

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Refusing to bind', preg_replace('/\s+/', ' ', $tester->getDisplay()) ?? '');
        $this->assertSame($before, $this->stateDirs());
    }

    public function testNonLoopbackHostUnderLandoWarnsAndProceeds(): void
    {
        putenv('LANDO=ON');
        $output = new BufferedOutput();
        $io = new SymfonyStyle(new \Symfony\Component\Console\Input\ArrayInput([]), $output);

        $ok = (new ReflectionMethod($this->command(), 'checkWatchPreconditions'))
            ->invoke($this->command(), $io, '0.0.0.0', false);

        $this->assertTrue($ok);
        $this->assertStringContainsString('Lando', $output->fetch());
    }

    public function testAllowRemoteWarnsThatSiteAndErrorsAreReachableFromTheNetwork(): void
    {
        $output = new BufferedOutput();
        $io = new SymfonyStyle(new \Symfony\Component\Console\Input\ArrayInput([]), $output);

        $ok = (new ReflectionMethod($this->command(), 'checkWatchPreconditions'))
            ->invoke($this->command(), $io, '0.0.0.0', true);

        $this->assertTrue($ok);
        $this->assertStringContainsString('--allow-remote', $output->fetch());
    }

    public function testMissingPublicDirectoryIsReportedBeforeAnyWatchChecks(): void
    {
        $this->removeDirectory($this->base . '/public');

        $tester = $this->runWatch('192.168.1.10');

        $this->assertStringContainsString('Public directory not found', $tester->getDisplay());
    }

    /**
     * @return array{0: DevServerCommand, 1: PrivateStateDir, 2: FakeBuildRunner, 3: BufferedOutput, 4: SymfonyStyle}
     */
    private function commandWithState(): array
    {
        $runner = new FakeBuildRunner();
        $command = $this->command($runner, new FakeClock(5000));
        $dir = PrivateStateDir::create();
        $this->dirs[] = $dir;
        (new ReflectionProperty($command, 'stateDir'))->setValue($command, $dir);
        $output = new BufferedOutput();
        $io = new SymfonyStyle(new \Symfony\Component\Console\Input\ArrayInput([]), $output);

        return [$command, $dir, $runner, $output, $io];
    }

    /**
     * @return array{v: int, status: string, error: string}
     */
    private function state(PrivateStateDir $dir): array
    {
        $data = json_decode((string) file_get_contents($dir->file('state.json')), true);
        $this->assertIsArray($data);

        return ['v' => (int) $data['v'], 'status' => (string) $data['status'], 'error' => (string) $data['error']];
    }

    private function finish(
        DevServerCommand $command,
        SymfonyStyle $io,
        FakeBuildRunner $runner,
        BuildRequest $request
    ): void {
        (new ReflectionMethod($command, 'finishBuild'))
            ->invoke($command, $io, $runner, $request, 4000, $this->base . '/app');
    }

    public function testSuccessfulBuildIncrementsVersionAndReportsOk(): void
    {
        [$command, $dir, $runner, $output, $io] = $this->commandWithState();
        $runner->finish(0, 'done');

        $this->finish($command, $io, $runner, new BuildRequest(false, [$this->base . '/content/a.md'], []));
        $this->finish($command, $io, $runner, new BuildRequest(false, [$this->base . '/content/a.md'], []));

        $this->assertSame(['v' => 3, 'status' => 'ok', 'error' => ''], $this->state($dir));
        $this->assertStringContainsString('OK 1.0s', $output->fetch());
    }

    public function testFailedBuildReportsFailedAndLeavesVersionUnchanged(): void
    {
        [$command, $dir, $runner, $output, $io] = $this->commandWithState();
        $runner->finish(0);
        $this->finish($command, $io, $runner, new BuildRequest(false, ['/x'], []));
        $output->fetch();

        $runner->finish(1, "Starting\nTemplate error: unexpected token\nmore");
        $this->finish($command, $io, $runner, new BuildRequest(false, ['/x'], []));

        $state = $this->state($dir);
        $this->assertSame(2, $state['v']);
        $this->assertSame('failed', $state['status']);
        $this->assertSame('Template error: unexpected token', $state['error']);
        $this->assertStringContainsString('FAILED', $output->fetch());
    }

    public function testSuccessAfterFailureClearsTheErrorAndBumpsVersion(): void
    {
        [$command, $dir, $runner, , $io] = $this->commandWithState();
        $runner->finish(1, 'Fatal error: boom');
        $this->finish($command, $io, $runner, new BuildRequest(false, ['/x'], []));
        $this->assertSame('failed', $this->state($dir)['status']);

        $runner->finish(0);
        $this->finish($command, $io, $runner, new BuildRequest(false, ['/x'], []));

        $this->assertSame(['v' => 2, 'status' => 'ok', 'error' => ''], $this->state($dir));
    }

    public function testErrorExtractionStripsAnsiCapsLinesAndFallsBackToTail(): void
    {
        [$command, $dir, $runner, , $io] = $this->commandWithState();
        $noisy = "\e[31mError one\e[0m\nError two\nException three\nfail four\nError five\nError six\nError seven";
        $runner->finish(1, $noisy);
        $this->finish($command, $io, $runner, new BuildRequest(false, ['/x'], []));

        $error = $this->state($dir)['error'];
        $this->assertSame("Error one\nError two\nException three\nfail four\nError five", $error);

        $runner->finish(1, "a\nb\nc\nd\ne\nf\ng");
        $this->finish($command, $io, $runner, new BuildRequest(false, ['/x'], []));
        $this->assertSame("c\nd\ne\nf\ng", $this->state($dir)['error']);
    }

    public function testDeletedSourceIsLoggedAsCleanRebuildWithCount(): void
    {
        [$command, , $runner, $output, $io] = $this->commandWithState();
        $runner->finish(0);

        $this->finish(
            $command,
            $io,
            $runner,
            new BuildRequest(true, [], [$this->base . '/app/content/old.md', $this->base . '/app/content/b.md'])
        );

        $log = $output->fetch();
        $this->assertStringContainsString('source deleted/renamed: --clean', $log);
        $this->assertStringContainsString('(+1 more)', $log);
        $this->assertStringContainsString('content/old.md', $log);
        $this->assertStringNotContainsString($this->base . '/app/content/old.md', $log);
    }

    public function testTemplateAndConfigChangesAreLoggedAsFullRerenders(): void
    {
        [$command, , $runner, $output, $io] = $this->commandWithState();
        $runner->finish(0);

        $this->finish($command, $io, $runner, new BuildRequest(false, [$this->base . '/templates/base.twig'], []));
        $this->assertStringContainsString('templates changed: full re-render', $output->fetch());

        $this->finish($command, $io, $runner, new BuildRequest(false, [$this->base . '/app/siteconfig.yaml'], []));
        $this->assertStringContainsString('config changed: full re-render', $output->fetch());

        $this->finish($command, $io, $runner, new BuildRequest(false, [$this->base . '/app/.env'], []));
        $this->assertStringContainsString('config changed: full re-render', $output->fetch());

        $this->finish($command, $io, $runner, new BuildRequest(false, [$this->base . '/content/a.md'], []));
        $this->assertStringNotContainsString('full re-render', $output->fetch());
    }

    public function testCleanupStopsTheBuildRunner(): void
    {
        $runner = new FakeBuildRunner();

        $this->command($runner)->cleanup();

        $this->assertTrue($runner->stopped);
    }

    public function testRouterSourceEmbedsWatchFlagStateFileAndHosts(): void
    {
        $source = $this->command()->buildRouterSource('localhost', true, '/priv/state.json');

        $this->assertStringContainsString("'/priv/state.json'", $source);
        $this->assertStringContainsString(", true, ", $source);
        $this->assertStringContainsString("'localhost'", $source);
        $this->assertStringContainsString(var_export($this->base . '/app', true), $source);
    }

    public function testRouterSourceIncludesLandoHostsOnlyWhenLandoIsOn(): void
    {
        putenv('LANDO_INFO=' . json_encode(['appserver' => ['urls' => ['https://my-site.lndo.site/']]]));

        $off = $this->command()->buildRouterSource('0.0.0.0', true, '/s.json');
        putenv('LANDO=ON');
        $on = $this->command()->buildRouterSource('0.0.0.0', true, '/s.json');

        $this->assertStringNotContainsString('my-site.lndo.site', $off);
        $this->assertStringContainsString('my-site.lndo.site', $on);
    }

    public function testGeneratedRouterInjectsAndGuardsStateEndpointWhenRunAsPhpDoes(): void
    {
        file_put_contents($this->base . '/public/index.html', '<html><body>hi</body></html>');
        $stateFile = $this->base . '/state.json';
        file_put_contents($stateFile, '{"v":4,"status":"ok","error":""}');
        $routerFile = $this->base . '/router.php';
        file_put_contents($routerFile, $this->command()->buildRouterSource('localhost', true, $stateFile));

        $page = $this->runGeneratedRouter($routerFile, '/', 'localhost:8000');
        $state = $this->runGeneratedRouter($routerFile, '/__staticforge/state', 'localhost:8000');
        $foreign = $this->runGeneratedRouter($routerFile, '/__staticforge/state', 'evil.example');

        $this->assertStringContainsString('sfdevLoaded', $page['stdout']);
        $this->assertSame(200, $page['status']);
        $this->assertSame('{"v":4,"status":"ok","error":""}', $state['stdout']);
        $this->assertSame(403, $foreign['status']);
        $this->assertStringNotContainsString('"v"', $foreign['stdout']);
    }

    /**
     * @return array{stdout: string, status: int}
     */
    private function runGeneratedRouter(string $routerFile, string $uri, string $host): array
    {
        $runner = $this->base . '/run_router.php';
        file_put_contents($runner, <<<'PHP'
<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = $argv[2];
$_SERVER['HTTP_HOST'] = $argv[3];
register_shutdown_function(static function (): void {
    fwrite(STDERR, 'STATUS=' . http_response_code());
});
include $argv[1];
PHP);
        $process = proc_open(
            [PHP_BINARY, $runner, $routerFile, $uri, $host],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        $this->assertIsResource($process);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        proc_close($process);
        preg_match('/STATUS=(\d+)/', $stderr, $m);

        return ['stdout' => $stdout, 'status' => (int) ($m[1] ?? 0)];
    }

    public function testServerIsLaunchedWithAnArgvArrayNeverAShellString(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 5) . '/src/Features/DevServer/Commands/DevServerCommand.php');

        $this->assertStringContainsString('proc_open(', $source);
        $this->assertDoesNotMatchRegularExpression('/\b(popen|shell_exec|exec|system|passthru)\s*\(/', $source);
        $this->assertStringNotContainsString('staticforge-devserver-router-', $source);
    }
}
