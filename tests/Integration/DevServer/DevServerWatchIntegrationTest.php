<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Integration\DevServer;

use EICC\StaticForge\Features\DevServer\Services\PrivateStateDir;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Starts the real `site:devserver --watch` (a child PHP process running php -S) against a
 * throwaway site in the system temp dir, on an unused loopback port, and talks to it over
 * HTTP. Nothing outside the temp site is touched; the child is always stopped in tearDown.
 */
class DevServerWatchIntegrationTest extends TestCase
{
    private const BOOT_TIMEOUT_S = 20;
    private const BUILD_TIMEOUT_S = 60;

    private string $app;
    private int $port = 0;
    /** @var resource|null */
    private $process = null;
    /** @var array<int, resource> */
    private array $pipes = [];
    private string $log = '';
    /** @var list<string> */
    private array $stateDirsBefore = [];
    private string $victim;

    protected function setUp(): void
    {
        if (PHP_OS_FAMILY === 'Windows' || !function_exists('proc_open')) {
            $this->markTestSkipped('Requires a POSIX system with proc_open');
        }

        $this->app = (string) realpath(sys_get_temp_dir()) . '/sf_devserver_it_' . bin2hex(random_bytes(4));
        $repo = dirname(__DIR__, 3);
        mkdir($this->app . '/bin', 0755, true);
        mkdir($this->app . '/content', 0755);
        mkdir($this->app . '/templates', 0755);
        $this->copyTree($repo . '/templates/sample', $this->app . '/templates/sample');
        file_put_contents(
            $this->app . '/bin/staticforge.php',
            '<?php require ' . var_export($repo . '/bin/staticforge.php', true) . ";\n"
        );
        file_put_contents($this->app . '/.env', implode("\n", [
            'SITE_NAME="Fixture"',
            'SITE_BASE_URL="http://localhost/"',
            'TEMPLATE="sample"',
            'SOURCE_DIR="' . $this->app . '/content"',
            'OUTPUT_DIR="' . $this->app . '/public"',
            'TEMPLATE_DIR="' . $this->app . '/templates"',
            'LOG_LEVEL="ERROR"',
            'LOG_FILE="' . $this->app . '/build.log"',
            '',
        ]));
        file_put_contents($this->app . '/content/index.md', "---\ntitle: Home\n---\n\nHello fixture world\n");
        file_put_contents($this->app . '/content/extra.md', "---\ntitle: Extra\n---\n\nExtra page body\n");
        $this->victim = $this->app . '/victim.txt';
        file_put_contents($this->victim, 'ORIGINAL');

        // The build fingerprint and incremental cache compare whole-second mtimes, so back-date
        // everything: an edit made moments after setUp must still look newer.
        $this->backdate($this->app . '/content');
        $this->backdate($this->app . '/templates');

        $initial = $this->runProcess([PHP_BINARY, $this->app . '/bin/staticforge.php', 'site:render', '--no-ansi']);
        $this->assertSame(0, $initial['code'], $initial['output']);
        $this->assertFileExists($this->app . '/public/index.html');
        $this->backdate($this->app . '/public');

        $this->stateDirsBefore = $this->stateDirs();
    }

    protected function tearDown(): void
    {
        $this->stopServer();
        if (isset($this->app)) {
            $this->removeTree($this->app);
        }
        foreach (array_diff($this->stateDirs(), $this->stateDirsBefore) as $leftover) {
            $this->removeTree($leftover);
        }
        parent::tearDown();
    }

    public function testServerInjectsScriptAnswersStateEndpointAndServes404(): void
    {
        $this->startServer();

        $page = $this->http('/');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Hello fixture world', $page['body']);
        $this->assertStringContainsString('sfdevLoaded', $page['body']);
        $this->assertSame(1, substr_count($page['body'], 'sfdevLoaded = true'));
        $this->assertSame((string) strlen($page['body']), $page['headers']['content-length'] ?? '');
        $this->assertSame('no-store', $page['headers']['cache-control'] ?? '');
        $this->assertStringStartsWith('text/html', $page['headers']['content-type'] ?? '');

        $state = $this->http('/__staticforge/state');
        $this->assertSame(200, $state['status']);
        $this->assertSame(['v' => 1, 'status' => 'ok', 'error' => ''], json_decode($state['body'], true));

        $missing = $this->http('/does-not-exist.html');
        $this->assertSame(404, $missing['status']);
        $this->assertStringContainsString('sfdevLoaded', $missing['body']);

        $asset = $this->http('/robots.txt');
        $this->assertSame(200, $asset['status']);
        $this->assertStringNotContainsString('sfdevLoaded', $asset['body']);
    }

    public function testStateEndpointRejectsForeignHostAndNonGet(): void
    {
        $this->startServer();

        $this->assertSame(403, $this->http('/__staticforge/state', ['Host: evil.example'])['status']);
        $this->assertSame(403, $this->http('/__staticforge/state', ['Host: localhost.evil.example'])['status']);
        $this->assertSame(405, $this->http('/__staticforge/state', [], 'POST')['status']);
        $this->assertSame(200, $this->http('/__staticforge/state', ['Host: localhost:' . $this->port])['status']);
    }

    public function testRawTraversalRequestsCannotEscapeTheDocroot(): void
    {
        $this->startServer();
        file_put_contents($this->app . '/secret.txt', 'TOP-SECRET');

        foreach (['/../secret.txt', '/%2e%2e/secret.txt', '/a/../../secret.txt', '/..%2fsecret.txt'] as $path) {
            $raw = $this->rawRequest($path);

            $this->assertStringNotContainsString('TOP-SECRET', $raw, $path);
        }
    }

    public function testSymlinkInsideDocrootPointingOutsideIsNotServed(): void
    {
        $this->startServer();
        symlink($this->victim, $this->app . '/public/leak.txt');

        $response = $this->http('/leak.txt');

        $this->assertSame(404, $response['status']);
        $this->assertStringNotContainsString('ORIGINAL', $response['body']);
    }

    public function testTemplateOnlyEditRebuildsAndChangesOutput(): void
    {
        $this->startServer();
        $base = $this->app . '/templates/sample/base.html.twig';
        $template = (string) file_get_contents($base);
        $this->assertStringNotContainsString('SF-TEMPLATE-MARKER', (string) $this->http('/')['body']);

        file_put_contents($base, str_replace('</body>', '<!-- SF-TEMPLATE-MARKER --></body>', $template));

        $state = $this->waitForVersion(2);
        $this->assertSame('ok', $state['status']);
        $this->assertStringContainsString('SF-TEMPLATE-MARKER', $this->http('/')['body']);
        $this->assertStringContainsString('templates changed: full re-render', $this->drainLog());
    }

    public function testContentEditRebuildsAndIsReportedInTheTerminalLog(): void
    {
        $this->startServer();

        file_put_contents($this->app . '/content/index.md', "---\ntitle: Home\n---\n\nEdited by the test\n");

        $this->waitForVersion(2);
        $this->assertStringContainsString('Edited by the test', $this->http('/')['body']);
        $log = $this->drainLog();
        $this->assertMatchesRegularExpression('/OK \d+\.\ds .*content\/index\.md/', $log);
        $this->assertStringNotContainsString('/__staticforge/', $log);
    }

    public function testDeletingASourceRunsACleanBuildThatRemovesTheOrphanPage(): void
    {
        $this->startServer();
        $this->assertSame(200, $this->http('/extra.html')['status']);

        unlink($this->app . '/content/extra.md');

        $this->waitForVersion(2);
        $this->assertSame(404, $this->http('/extra.html')['status']);
        $this->assertSame(200, $this->http('/')['status']);
        $this->assertStringContainsString('source deleted/renamed: --clean', $this->drainLog());
    }

    public function testFailedBuildIsReportedWithoutBumpingTheVersionAndRecovers(): void
    {
        $this->startServer();
        $base = $this->app . '/templates/sample/base.html.twig';
        $good = (string) file_get_contents($base);

        file_put_contents($base, $good . "\n{% if %}\n");
        $failed = $this->waitForState(static fn(array $s): bool => $s['status'] === 'failed');

        $this->assertSame(1, $failed['v']);
        $this->assertNotSame('', $failed['error']);
        $this->assertLessThanOrEqual(500, mb_strlen($failed['error']));
        $this->assertLessThanOrEqual(5, substr_count($failed['error'], "\n") + 1);
        $this->assertStringNotContainsString(dirname(__DIR__, 3), $failed['error']);
        $this->assertStringContainsString('Hello fixture world', $this->http('/')['body']);

        file_put_contents($base, $good);
        $recovered = $this->waitForState(static fn(array $s): bool => $s['status'] === 'ok' && $s['v'] === 2);

        $this->assertSame('', $recovered['error']);
    }

    public function testStopCleansUpProcessesPrivateDirAndNeverFollowsTheOldPredictablePath(): void
    {
        if (!function_exists('pcntl_signal')) {
            $this->markTestSkipped('ext-pcntl is required for the command to handle SIGTERM and clean up');
        }
        $this->startServer();
        $created = array_values(array_diff($this->stateDirs(), $this->stateDirsBefore));
        $this->assertCount(1, $created);
        $this->assertSame(0700, fileperms($created[0]) & 0777);
        $this->assertFileExists($created[0] . '/router.php');
        $this->assertFileExists($created[0] . '/state.json');

        $this->assertNotNull($this->process);
        proc_terminate($this->process, SIGTERM);
        $this->assertTrue($this->waitForExit(10), 'command did not exit on SIGTERM');

        $this->assertFalse($this->portOpen(), 'php -S is still listening after the command exited');
        $this->assertSame([], array_values(array_diff($this->stateDirs(), $this->stateDirsBefore)));
        $this->assertSame('ORIGINAL', file_get_contents($this->victim));
        $this->assertTrue(is_link($this->oldPath()));
        $this->assertSame($this->victim, readlink($this->oldPath()));
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function terminalSignalProvider(): array
    {
        return [
            'SIGHUP (closed terminal)' => [1],
            'SIGQUIT' => [3],
            'SIGINT' => [2],
        ];
    }

    #[DataProvider('terminalSignalProvider')]
    public function testEveryTerminalSignalStopsTheServerChildAndRemovesThePrivateDir(int $signal): void
    {
        if (!function_exists('pcntl_signal')) {
            $this->markTestSkipped('ext-pcntl is required for the command to handle signals and clean up');
        }
        $this->startServer();
        $created = array_values(array_diff($this->stateDirs(), $this->stateDirsBefore));
        $this->assertCount(1, $created);
        $this->assertNotNull($this->process);

        proc_terminate($this->process, $signal);

        $this->assertTrue($this->waitForExit(10), "command did not exit on signal {$signal}");
        $this->assertTrue(
            $this->waitUntil(fn(): bool => !$this->portOpen(), 5),
            "php -S is still listening after signal {$signal}"
        );
        $this->assertSame([], array_values(array_diff($this->stateDirs(), $this->stateDirsBefore)));
        $this->assertDirectoryDoesNotExist($created[0]);
        $this->assertSame([], $this->serverChildren(), 'a php -S child survived the signal');
    }

    public function testServerChildEnvironmentHasNoSecretsButKeepsPathHomeTmpdirAndLang(): void
    {
        if (!is_readable('/proc/self/environ')) {
            $this->markTestSkipped('/proc is unavailable, cannot inspect the child environment');
        }
        $env = getenv();
        $env['SFTP_PASSWORD'] = 'planted-sftp-secret';
        $env['SFTP_PRIVATE_KEY_PASSPHRASE'] = 'planted-passphrase';
        $env['GPG_KEYS'] = 'planted-gpg';
        $env['LANDO_TEST_SECRET'] = 'planted-lando';
        $env['TMPDIR'] = sys_get_temp_dir();
        $env['LANG'] = 'C.UTF-8';
        $env['LC_ALL'] = 'C.UTF-8';
        $env['HOME'] ??= sys_get_temp_dir();
        $env['PATH'] ??= '/usr/local/bin:/usr/bin:/bin';
        unset($env['LANDO']);

        $this->startServer($env);

        $children = $this->serverChildren();
        $this->assertCount(1, $children, 'expected exactly one php -S child');
        $raw = @file_get_contents('/proc/' . $children[0] . '/environ');
        if ($raw === false) {
            $this->markTestSkipped('/proc/<pid>/environ is not readable');
        }
        $child = [];
        foreach (array_filter(explode("\0", $raw), static fn(string $l): bool => $l !== '') as $line) {
            [$name, $value] = array_pad(explode('=', $line, 2), 2, '');
            $child[$name] = $value;
        }

        $this->assertStringNotContainsString('planted-', $raw);
        foreach (array_keys($child) as $name) {
            $this->assertDoesNotMatchRegularExpression('/^(SFTP_|LANDO|GPG_KEYS)/', $name);
        }
        foreach (['PATH', 'HOME', 'TMPDIR', 'LANG', 'LC_ALL'] as $kept) {
            $this->assertArrayHasKey($kept, $child, "{$kept} must survive in the server child");
            $this->assertSame($env[$kept], $child[$kept]);
        }
        $this->assertSame(200, $this->http('/')['status'], 'server must still work with the reduced environment');
    }

    public function testServerBindFailureMakesTheCommandExitWithFailureAndPrintTheReason(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertNotFalse($socket);
        $name = (string) stream_socket_get_name($socket, false);
        $port = (int) substr($name, (int) strrpos($name, ':') + 1);
        fclose($socket);

        // 10.255.255.1 is not an address of this host, so php -S cannot bind it.
        $result = $this->runProcess([
            PHP_BINARY, $this->app . '/bin/staticforge.php', 'site:devserver',
            '--host=10.255.255.1', '--port=' . $port, '--no-ansi',
        ]);

        $this->assertSame(1, $result['code'], $result['output']);
        $this->assertStringContainsString('exited unexpectedly', $result['output']);
        $this->assertSame([], array_values(array_diff($this->stateDirs(), $this->stateDirsBefore)));
    }

    public function testWatchModeDoesNotBuildAtStartupOrWhenNothingChanges(): void
    {
        $this->startServer();
        $before = $this->http('/__staticforge/state');

        usleep(2500000);

        $after = $this->http('/__staticforge/state');
        $this->assertSame(['v' => 1, 'status' => 'ok', 'error' => ''], json_decode($before['body'], true));
        $this->assertSame($before['body'], $after['body']);
        $this->assertDoesNotMatchRegularExpression('/\b(OK|FAILED) \d+\.\ds/', $this->drainLog());
    }

    /**
     * @return list<int> pids of php -S processes whose parent is the running command
     */
    private function serverChildren(): array
    {
        $parent = $this->process !== null ? (int) proc_get_status($this->process)['pid'] : 0;
        $found = [];
        foreach (glob('/proc/[0-9]*/stat') ?: [] as $statFile) {
            $stat = @file_get_contents($statFile);
            if ($stat === false || preg_match('/^(\d+) \(.*\) \S (\d+) /s', $stat, $m) !== 1) {
                continue;
            }
            $cmdline = (string) @file_get_contents(dirname($statFile) . '/cmdline');
            if (str_contains($cmdline, "\0-S\0") && str_contains($cmdline, $this->app . '/public')) {
                if ($parent === 0 || (int) $m[2] === $parent) {
                    $found[] = (int) $m[1];
                }
            }
        }

        return $found;
    }

    private function oldPath(): string
    {
        return sys_get_temp_dir() . '/staticforge-devserver-router-' . $this->pid() . '.php';
    }

    private function pid(): int
    {
        $this->assertNotNull($this->process);

        return (int) proc_get_status($this->process)['pid'];
    }

    /**
     * @param array<string, string>|null $env Full environment for the command; null inherits ours
     */
    private function startServer(?array $env = null): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $this->assertNotFalse($socket, (string) $errstr);
        $name = (string) stream_socket_get_name($socket, false);
        $this->port = (int) substr($name, (int) strrpos($name, ':') + 1);
        fclose($socket);

        $process = proc_open(
            [
                PHP_BINARY, $this->app . '/bin/staticforge.php', 'site:devserver',
                '--watch', '--host=127.0.0.1', '--port=' . $this->port, '--no-ansi',
            ],
            [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->app,
            $env
        );
        $this->assertIsResource($process);
        $this->process = $process;
        $this->pipes = [1 => $pipes[1], 2 => $pipes[2]];
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        // An attacker-planted link at the pre-3.4 predictable path must never be written through.
        symlink($this->victim, $this->oldPath());

        $up = $this->waitUntil(fn(): bool => $this->portOpen() || !$this->isRunning(), self::BOOT_TIMEOUT_S);
        $this->assertTrue($up && $this->isRunning(), 'server did not start: ' . $this->drainLog());
    }

    private function stopServer(): void
    {
        if ($this->process === null) {
            return;
        }

        $pid = $this->pid();
        if ($this->isRunning()) {
            proc_terminate($this->process, SIGTERM);
            $this->waitForExit(5);
        }
        if ($this->isRunning()) {
            @exec('pkill -KILL -P ' . $pid);
            proc_terminate($this->process, SIGKILL);
            $this->waitForExit(3);
        }
        foreach ($this->pipes as $pipe) {
            fclose($pipe);
        }
        proc_close($this->process);
        $this->process = null;
        $this->pipes = [];
        $old = sys_get_temp_dir() . '/staticforge-devserver-router-' . $pid . '.php';
        if (is_link($old)) {
            unlink($old);
        }
    }

    private function waitForExit(int $seconds): bool
    {
        return $this->waitUntil(fn(): bool => !$this->isRunning(), $seconds);
    }

    private function isRunning(): bool
    {
        return $this->process !== null && proc_get_status($this->process)['running'];
    }

    private function portOpen(): bool
    {
        $socket = @fsockopen('127.0.0.1', $this->port, $errno, $errstr, 0.5);
        if ($socket === false) {
            return false;
        }
        fclose($socket);

        return true;
    }

    private function drainLog(): string
    {
        foreach ($this->pipes as $pipe) {
            $chunk = stream_get_contents($pipe);
            $this->log .= is_string($chunk) ? $chunk : '';
        }

        return $this->log;
    }

    /**
     * @param callable(): bool $condition
     */
    private function waitUntil(callable $condition, int $seconds): bool
    {
        $deadline = microtime(true) + $seconds;
        do {
            if ($condition()) {
                return true;
            }
            usleep(100000);
        } while (microtime(true) < $deadline);

        return false;
    }

    /**
     * @param callable(array{v: int, status: string, error: string}): bool $predicate
     * @return array{v: int, status: string, error: string}
     */
    private function waitForState(callable $predicate): array
    {
        $last = ['v' => -1, 'status' => '', 'error' => ''];
        $this->waitUntil(function () use ($predicate, &$last): bool {
            $response = $this->http('/__staticforge/state');
            $data = json_decode($response['body'], true);
            if (!is_array($data)) {
                return false;
            }
            $last = ['v' => (int) $data['v'], 'status' => (string) $data['status'], 'error' => (string) $data['error']];

            return $predicate($last);
        }, self::BUILD_TIMEOUT_S);
        $this->assertTrue($predicate($last), 'timed out waiting for state; last=' . json_encode($last) . ' log=' . $this->drainLog());

        return $last;
    }

    /**
     * @return array{v: int, status: string, error: string}
     */
    private function waitForVersion(int $version): array
    {
        return $this->waitForState(static fn(array $s): bool => $s['v'] >= $version && $s['status'] === 'ok');
    }

    /**
     * @param list<string> $headers
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function http(string $path, array $headers = [], string $method = 'GET'): array
    {
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'ignore_errors' => true,
            'timeout' => 10,
            'follow_location' => 0,
            'content' => $method === 'POST' ? '' : null,
        ]]);
        $body = @file_get_contents('http://127.0.0.1:' . $this->port . $path, false, $context);
        $raw = http_get_last_response_headers() ?? [];

        $status = 0;
        $parsed = [];
        foreach ($raw as $line) {
            if (preg_match('#^HTTP/\S+ (\d{3})#', $line, $m) === 1) {
                $status = (int) $m[1];
                $parsed = [];
            } elseif (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $parsed[strtolower(trim($name))] = trim($value);
            }
        }

        return ['status' => $status, 'headers' => $parsed, 'body' => is_string($body) ? $body : ''];
    }

    private function rawRequest(string $path): string
    {
        $socket = fsockopen('127.0.0.1', $this->port, $errno, $errstr, 5);
        $this->assertNotFalse($socket, (string) $errstr);
        fwrite($socket, "GET {$path} HTTP/1.1\r\nHost: localhost\r\nConnection: close\r\n\r\n");
        stream_set_timeout($socket, 5);
        $response = (string) stream_get_contents($socket);
        fclose($socket);

        return $response;
    }

    /**
     * @param list<string> $argv
     * @return array{code: int, output: string}
     */
    private function runProcess(array $argv): array
    {
        $process = proc_open($argv, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->app);
        $this->assertIsResource($process);
        $output = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
        $code = proc_close($process);

        return ['code' => $code, 'output' => $output];
    }

    /**
     * @return list<string>
     */
    private function stateDirs(): array
    {
        return glob(sys_get_temp_dir() . "/" . PrivateStateDir::PREFIX . "*") ?: [];
    }

    private function copyTree(string $from, string $to): void
    {
        mkdir($to, 0755, true);
        foreach (array_diff((array) scandir($from), ['.', '..']) as $entry) {
            is_dir($from . '/' . $entry)
                ? $this->copyTree($from . '/' . $entry, $to . '/' . $entry)
                : copy($from . '/' . $entry, $to . '/' . $entry);
        }
    }

    private function removeTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (array_diff((array) scandir($path), ['.', '..']) as $entry) {
            $this->removeTree($path . '/' . $entry);
        }
        rmdir($path);
    }

    private function backdate(string $path): void
    {
        if (is_dir($path) && !is_link($path)) {
            foreach (array_diff((array) scandir($path), ['.', '..']) as $entry) {
                $this->backdate($path . '/' . $entry);
            }
        }
        touch($path, time() - 100);
    }
}
