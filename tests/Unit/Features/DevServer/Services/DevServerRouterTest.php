<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\DevServer\Services;

use EICC\StaticForge\Features\DevServer\Services\ClientScript;
use EICC\StaticForge\Features\DevServer\Services\DevServerRouter;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class DevServerRouterTest extends UnitTestCase
{
    use SymlinkSafeCleanup;

    private string $base;
    private string $docroot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = (string) realpath(sys_get_temp_dir()) . '/sf_router_' . bin2hex(random_bytes(4));
        $this->docroot = $this->base . '/public';
        mkdir($this->docroot, 0755, true);
        file_put_contents($this->base . '/secret.txt', 'SECRET');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->base);
        parent::tearDown();
    }

    private function router(bool $watch): DevServerRouter
    {
        return new DevServerRouter($this->docroot, $watch, null, ['localhost'], '/app');
    }

    private function page(string $name, string $html): void
    {
        $dir = dirname($this->docroot . '/' . $name);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($this->docroot . '/' . $name, $html);
    }

    public function testHtmlIsInjectedBeforeClosingBodyInWatchMode(): void
    {
        $this->page('index.html', '<html><body><p>Hi</p></body></html>');

        $response = $this->router(true)->handle('GET', '/index.html');

        $this->assertNotNull($response);
        $this->assertSame(200, $response->status);
        $this->assertSame(
            '<html><body><p>Hi</p>' . ClientScript::tag() . '</body></html>',
            $response->body
        );
    }

    public function testInjectedResponseCarriesTypeLengthAndNoStoreHeaders(): void
    {
        $this->page('index.html', '<body>x</body>');

        $response = $this->router(true)->handle('GET', '/');

        $this->assertNotNull($response);
        $this->assertSame('text/html; charset=UTF-8', $response->headers['Content-Type']);
        $this->assertSame((string) strlen($response->body), $response->headers['Content-Length']);
        $this->assertSame('no-store', $response->headers['Cache-Control']);
    }

    public function testContentLengthCountsBytesNotCharacters(): void
    {
        $this->page('index.html', '<body>héllo ✓</body>');

        $response = $this->router(true)->handle('GET', '/');

        $this->assertNotNull($response);
        $this->assertSame((string) strlen($response->body), $response->headers['Content-Length']);
        $this->assertGreaterThan(mb_strlen($response->body), strlen($response->body));
    }

    public function testScriptIsAppendedWhenThereIsNoClosingBody(): void
    {
        $this->page('frag.html', '<p>fragment</p>');

        $response = $this->router(true)->handle('GET', '/frag.html');

        $this->assertNotNull($response);
        $this->assertSame('<p>fragment</p>' . ClientScript::tag(), $response->body);
    }

    public function testScriptGoesBeforeTheLastClosingBodyOnly(): void
    {
        $this->page('two.html', '<body>a</body><pre>&lt;/body&gt;</pre><body>b</body>');

        $response = $this->router(true)->handle('GET', '/two.html');

        $this->assertNotNull($response);
        $this->assertSame(1, substr_count($response->body, ClientScript::tag()));
        $this->assertStringEndsWith(ClientScript::tag() . '</body>', $response->body);
        $this->assertStringStartsWith('<body>a</body>', $response->body);
    }

    public function testClosingBodyMatchIsCaseInsensitive(): void
    {
        $this->page('upper.html', '<BODY>x</BODY>');

        $response = $this->router(true)->handle('GET', '/upper.html');

        $this->assertNotNull($response);
        $this->assertSame('<BODY>x' . ClientScript::tag() . '</BODY>', $response->body);
    }

    public function testHtmExtensionIsAlsoInjected(): void
    {
        $this->page('old.htm', '<body></body>');

        $response = $this->router(true)->handle('GET', '/old.htm');

        $this->assertNotNull($response);
        $this->assertStringContainsString('sfdevLoaded', $response->body);
    }

    public function testNothingIsInjectedWithoutWatch(): void
    {
        $this->page('index.html', '<body>x</body>');

        $this->assertNull($this->router(false)->handle('GET', '/index.html'));
        $this->assertNull($this->router(false)->handle('GET', '/'));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function nonHtmlProvider(): array
    {
        return [
            'css' => ['style.css', 'body{}'],
            'js' => ['app.js', 'alert(1)'],
            'json' => ['search.json', '{}'],
            'xml' => ['sitemap.xml', '<urlset/>'],
            'txt' => ['robots.txt', 'User-agent: *'],
            'html in name only' => ['page.html.txt', '<body></body>'],
            'no extension' => ['LICENSE', '<body></body>'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('nonHtmlProvider')]
    public function testNonHtmlIsLeftToTheBuiltInServerAndNeverInjected(string $name, string $contents): void
    {
        $this->page($name, $contents);

        $this->assertNull($this->router(true)->handle('GET', '/' . $name));
        $this->assertNull($this->router(false)->handle('GET', '/' . $name));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function traversalProvider(): array
    {
        return [
            'dot dot' => ['/../secret.txt'],
            'encoded dot dot' => ['/%2e%2e/secret.txt'],
            'mixed case encoded' => ['/%2E%2e/secret.txt'],
            'encoded slash' => ['/..%2fsecret.txt'],
            'deep' => ['/a/../../secret.txt'],
            'etc passwd' => ['/x/../../../../../../etc/passwd'],
            'nul byte' => ['/index.html%00.txt'],
            'nul byte then traversal' => ['/%00/../secret.txt'],
            'malformed uri' => ['///'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('traversalProvider')]
    public function testTraversalAndNulReturn404InBothModes(string $uri): void
    {
        $this->page('index.html', '<body>ok</body>');

        foreach ([true, false] as $watch) {
            $response = $this->router($watch)->handle('GET', $uri);

            $this->assertNotNull($response, $uri);
            $this->assertSame(404, $response->status, $uri);
            $this->assertStringNotContainsString('SECRET', $response->body, $uri);
        }
    }

    public function testSymlinkedFileInDocrootPointingOutsideIs404NotNativeServing(): void
    {
        symlink($this->base . '/secret.txt', $this->docroot . '/leak.txt');
        symlink($this->base . '/secret.txt', $this->docroot . '/leak.html');

        foreach ([true, false] as $watch) {
            foreach (['/leak.txt', '/leak.html'] as $uri) {
                $response = $this->router($watch)->handle('GET', $uri);

                $this->assertNotNull($response, $uri);
                $this->assertSame(404, $response->status, $uri);
                $this->assertStringNotContainsString('SECRET', $response->body);
            }
        }
    }

    public function testSymlinkedDirectoryPointingOutsideIs404(): void
    {
        mkdir($this->base . '/outside');
        file_put_contents($this->base . '/outside/index.html', 'OUTSIDE');
        file_put_contents($this->base . '/outside/data.txt', 'OUTSIDE');
        symlink($this->base . '/outside', $this->docroot . '/dirlink');

        foreach (['/dirlink/', '/dirlink', '/dirlink/data.txt'] as $uri) {
            $response = $this->router(false)->handle('GET', $uri);

            $this->assertNotNull($response, $uri);
            $this->assertSame(404, $response->status, $uri);
        }
    }

    public function testSymlinkInsideDocrootToAnotherDocrootFileIsAllowed(): void
    {
        $this->page('real.txt', 'R');
        symlink($this->docroot . '/real.txt', $this->docroot . '/alias.txt');

        $this->assertNull($this->router(false)->handle('GET', '/alias.txt'));
    }

    public function testDocrootPrefixSiblingIsNotTreatedAsInsideTheDocroot(): void
    {
        mkdir($this->base . '/public-evil');
        file_put_contents($this->base . '/public-evil/x.txt', 'EVIL');

        $response = $this->router(false)->handle('GET', '/../public-evil/x.txt');

        $this->assertNotNull($response);
        $this->assertSame(404, $response->status);
    }

    public function testRootAndDirectoriesMapToIndexHtml(): void
    {
        $this->page('index.html', '<body>home</body>');
        $this->page('docs/index.html', '<body>docs</body>');

        $home = $this->router(true)->handle('GET', '/');
        $docs = $this->router(true)->handle('GET', '/docs/');

        $this->assertNotNull($home);
        $this->assertStringContainsString('home', $home->body);
        $this->assertNotNull($docs);
        $this->assertStringContainsString('docs', $docs->body);
    }

    public function testDirectoryWithAndWithoutTrailingSlashServeTheSameIndex(): void
    {
        $this->page('foo/index.html', '<body>foo</body>');

        $with = $this->router(true)->handle('GET', '/foo/');
        $without = $this->router(true)->handle('GET', '/foo');

        $this->assertNotNull($with);
        $this->assertNotNull($without);
        $this->assertSame(200, $without->status);
        $this->assertSame($with->body, $without->body);
        $this->assertNull($this->router(false)->handle('GET', '/foo/'));
        $this->assertNull($this->router(false)->handle('GET', '/foo'));
    }

    public function testDirectoryWithoutIndexIs404(): void
    {
        mkdir($this->docroot . '/empty');

        $response = $this->router(false)->handle('GET', '/empty/');

        $this->assertNotNull($response);
        $this->assertSame(404, $response->status);
    }

    public function testBareFileNameWithoutFileIs404(): void
    {
        $this->page('foo.html', '<body></body>');

        $response = $this->router(false)->handle('GET', '/foo');

        $this->assertNotNull($response);
        $this->assertSame(404, $response->status);
    }

    public function testQueryStringDoesNotAffectFileSelection(): void
    {
        $this->page('a.html', '<body>a</body>');

        $response = $this->router(true)->handle('GET', '/a.html?x=../../secret.txt#frag');

        $this->assertNotNull($response);
        $this->assertSame(200, $response->status);
    }

    public function testEncodedFileNamesAreDecodedBeforeLookup(): void
    {
        $this->page('my page.html', '<body>spaced</body>');

        $response = $this->router(true)->handle('GET', '/my%20page.html');

        $this->assertNotNull($response);
        $this->assertStringContainsString('spaced', $response->body);
    }

    public function testMissingFileWithSiteFourOhFourServesItWithStatus404(): void
    {
        $this->page('404.html', '<body>SITE 404</body>');

        $response = $this->router(false)->handle('GET', '/missing');

        $this->assertNotNull($response);
        $this->assertSame(404, $response->status);
        $this->assertSame('<body>SITE 404</body>', $response->body);
        $this->assertSame('text/html; charset=UTF-8', $response->headers['Content-Type']);
        $this->assertArrayNotHasKey('Content-Length', $response->headers);
    }

    public function testFourOhFourPageIsInjectedInWatchMode(): void
    {
        $this->page('404.html', '<body>SITE 404</body>');

        $response = $this->router(true)->handle('GET', '/missing');

        $this->assertNotNull($response);
        $this->assertSame(404, $response->status);
        $this->assertStringContainsString('sfdevLoaded', $response->body);
        $this->assertSame((string) strlen($response->body), $response->headers['Content-Length']);
    }

    public function testFourOhFourIsReadFromTheFixedDocrootPathOnly(): void
    {
        file_put_contents($this->base . '/404.html', 'OUTSIDE 404');

        $response = $this->router(false)->handle('GET', '/../404.html');

        $this->assertNotNull($response);
        $this->assertSame(404, $response->status);
        $this->assertStringNotContainsString('OUTSIDE 404', $response->body);
        $this->assertStringContainsString('Page Not Found', $response->body);
    }

    public function testSymlinkedFourOhFourPointingOutsideFallsBackToBuiltInPage(): void
    {
        file_put_contents($this->base . '/outside404.html', 'OUTSIDE 404');
        symlink($this->base . '/outside404.html', $this->docroot . '/404.html');

        $response = $this->router(false)->handle('GET', '/missing');

        $this->assertNotNull($response);
        $this->assertSame(404, $response->status);
        $this->assertStringNotContainsString('OUTSIDE 404', $response->body);
    }

    public function testFallbackPageEscapesTheRequestedPath(): void
    {
        $response = $this->router(false)->handle('GET', '/<img src=x onerror=alert(1)>"\'');

        $this->assertNotNull($response);
        $this->assertSame(404, $response->status);
        $this->assertStringNotContainsString('<img src=x', $response->body);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;&quot;&#039;', $response->body);
    }

    public function testFallbackPageDoesNotExpandPlaceholdersFromTheRequest(): void
    {
        $response = $this->router(false)->handle('GET', '/%25%25URL%25%25');

        $this->assertNotNull($response);
        $this->assertStringNotContainsString('%%URL%%%%', $response->body);
    }

    public function testStateEndpointDoesNotExistWithoutWatch(): void
    {
        $response = $this->router(false)->handle('GET', '/__staticforge/state', 'localhost');

        $this->assertNotNull($response);
        $this->assertSame(404, $response->status);
        $this->assertStringNotContainsString('"status"', $response->body);
    }

    public function testDispatchReturnsFalseForNativeServing(): void
    {
        $this->page('style.css', 'x');

        $result = $this->router(false)->dispatch(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/style.css']);

        $this->assertFalse($result);
    }

    public function testDispatchEmitsStatusAndBodyForRouterHandledRequests(): void
    {
        $this->page('404.html', 'SITE 404');
        $script = $this->base . '/dispatch.php';
        file_put_contents($script, '<?php require ' . var_export(dirname(__DIR__, 5) . '/vendor/autoload.php', true)
            . '; $r = new \\EICC\\StaticForge\\Features\\DevServer\\Services\\DevServerRouter('
            . var_export($this->docroot, true) . ');'
            . ' $ret = $r->dispatch(["REQUEST_METHOD" => "GET", "REQUEST_URI" => "/nope"]);'
            . ' fwrite(STDERR, "RET=" . var_export($ret, true) . " STATUS=" . http_response_code());');

        $process = proc_open([PHP_BINARY, $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($process);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        proc_close($process);

        $this->assertSame('SITE 404', $stdout);
        $this->assertStringContainsString('RET=true STATUS=404', $stderr);
    }
}
