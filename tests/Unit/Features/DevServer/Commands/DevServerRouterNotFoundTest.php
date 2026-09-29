<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\DevServer\Commands;

use EICC\StaticForge\Features\DevServer\Commands\DevServerCommand;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

/**
 * The router is a PHP source string written to disk for `php -S`. It is inspected
 * statically and executed in a subprocess against a temp docroot (no server, no network).
 */
class DevServerRouterNotFoundTest extends UnitTestCase
{
    private string $docroot;
    private string $runner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->docroot = sys_get_temp_dir() . '/staticforge_router_docroot_' . uniqid();
        mkdir($this->docroot, 0755, true);
        $this->runner = sys_get_temp_dir() . '/staticforge_router_runner_' . uniqid() . '.php';
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->docroot);
        if (is_file($this->runner)) {
            unlink($this->runner);
        }
        parent::tearDown();
    }

    private function routerSource(): string
    {
        $command = new DevServerCommand($this->container);
        $method = new \ReflectionMethod($command, 'getRouterTemplate');

        return (string) $method->invoke($command);
    }

    /**
     * @return array{status: int, body: string, returnedFalse: bool}
     */
    private function runRouter(string $requestUri): array
    {
        $routerFile = $this->docroot . '/../' . basename($this->runner, '.php') . '_router.php';
        file_put_contents($routerFile, $this->routerSource());

        $runnerSource = <<<'PHP'
<?php
$_SERVER['REQUEST_URI'] = $argv[3];
chdir($argv[2]);
register_shutdown_function(static function (): void {
    fwrite(STDERR, 'STATUS=' . http_response_code());
});
$result = include $argv[1];
if ($result === false) {
    fwrite(STDERR, 'RETURNED_FALSE');
}
PHP;
        file_put_contents($this->runner, $runnerSource);

        $process = proc_open(
            [PHP_BINARY, $this->runner, $routerFile, $this->docroot, $requestUri],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        $this->assertIsResource($process);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        proc_close($process);
        unlink($routerFile);

        preg_match('/STATUS=(\d+)/', $stderr, $m);

        return [
            'status' => (int) ($m[1] ?? 0),
            'body' => $stdout,
            'returnedFalse' => str_contains($stderr, 'RETURNED_FALSE'),
        ];
    }

    public function testRouterServesFixedPath404HtmlWithStatus404(): void
    {
        $source = $this->routerSource();

        $this->assertStringContainsString('http_response_code(404)', $source);
        $this->assertStringContainsString('getcwd() . "/404.html"', $source);
        $this->assertStringContainsString('readfile($notFoundPage)', $source);
    }

    public function testRouterStillEscapesRequestPathOnFallbackPage(): void
    {
        $this->assertStringContainsString(
            'htmlspecialchars($requestUri, ENT_QUOTES, "UTF-8")',
            $this->routerSource()
        );
    }

    public function testRouterSourceIsValidPhp(): void
    {
        $file = $this->docroot . '/router_lint.php';
        file_put_contents($file, $this->routerSource());
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);

        $this->assertSame(0, $code, implode("\n", $out));
    }

    public function testMissingUrlServesSites404HtmlBodyWithStatus404(): void
    {
        $page = "<!DOCTYPE html><html><body><h1>Custom &amp; 404</h1></body></html>\n";
        file_put_contents($this->docroot . '/404.html', $page);

        $result = $this->runRouter('/no/such/page');

        $this->assertSame(404, $result['status']);
        $this->assertSame($page, $result['body']);
    }

    public function testHostilePathNeverSelectsWhichFileIsServed(): void
    {
        file_put_contents($this->docroot . '/404.html', 'SITE 404');
        file_put_contents($this->docroot . '/secret.txt', 'SECRET');

        $hostile = ['/../secret.txt', '/%2e%2e/secret.txt', '/x/../../../etc/passwd', '/<script>alert(1)</script>'];
        foreach ($hostile as $uri) {
            $result = $this->runRouter($uri);

            $this->assertSame('SITE 404', $result['body'], $uri);
            $this->assertSame(404, $result['status'], $uri);
        }
    }

    public function testFallbackPageEscapesHostilePathWhenSiteHasNo404Html(): void
    {
        $result = $this->runRouter('/<script>alert("x")</script>');

        $this->assertSame(404, $result['status']);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', $result['body']);
        $this->assertStringNotContainsString('<script>', $result['body']);
    }

    public function testExistingFileIsLeftToTheBuiltInServer(): void
    {
        file_put_contents($this->docroot . '/404.html', 'SITE 404');
        file_put_contents($this->docroot . '/real.html', 'REAL');

        $result = $this->runRouter('/real.html');

        $this->assertTrue($result['returnedFalse']);
        $this->assertSame('', $result['body']);
    }

    public function testDirectoryWithIndexIsLeftToTheBuiltInServer(): void
    {
        mkdir($this->docroot . '/docs');
        file_put_contents($this->docroot . '/docs/index.html', 'DOCS');
        file_put_contents($this->docroot . '/404.html', 'SITE 404');

        $result = $this->runRouter('/docs/');

        $this->assertTrue($result['returnedFalse']);
    }
}
