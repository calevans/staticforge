<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\DevServer\Services;

use EICC\StaticForge\Features\DevServer\Services\DevServerRouter;
use EICC\StaticForge\Features\DevServer\Services\RouterResponse;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class DevServerRouterStateEndpointTest extends UnitTestCase
{
    private const APP_ROOT = '/srv/site';

    private string $base;
    private string $stateFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = (string) realpath(sys_get_temp_dir()) . '/sf_state_' . bin2hex(random_bytes(4));
        mkdir($this->base . '/public', 0755, true);
        $this->stateFile = $this->base . '/state.json';
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->base);
        parent::tearDown();
    }

    /**
     * @param list<string> $allowed
     */
    private function router(array $allowed = ['localhost', '127.0.0.1', '[::1]', 'myhost.test']): DevServerRouter
    {
        return new DevServerRouter($this->base . '/public', true, $this->stateFile, $allowed, self::APP_ROOT);
    }

    private function get(string $host = 'localhost:8000', string $method = 'GET'): RouterResponse
    {
        $response = $this->router()->handle($method, '/__staticforge/state', $host);
        $this->assertNotNull($response);

        return $response;
    }

    /**
     * @return array{v: int, status: string, error: string}
     */
    private function json(RouterResponse $response): array
    {
        $data = json_decode($response->body, true);
        $this->assertIsArray($data);
        $this->assertIsInt($data['v']);
        $this->assertIsString($data['status']);
        $this->assertIsString($data['error']);

        return ['v' => $data['v'], 'status' => $data['status'], 'error' => $data['error']];
    }

    public function testStateBeforeFirstBuildIsVersionZeroOk(): void
    {
        $response = $this->get();

        $this->assertSame(200, $response->status);
        $this->assertSame(['v' => 0, 'status' => 'ok', 'error' => ''], $this->json($response));
    }

    public function testStateFileContentsAreReported(): void
    {
        file_put_contents($this->stateFile, '{"v":7,"status":"building","error":""}');

        $this->assertSame(['v' => 7, 'status' => 'building', 'error' => ''], $this->json($this->get()));
    }

    public function testResponseIsJsonNoStoreAndHasNoCorsHeader(): void
    {
        $response = $this->get();

        $this->assertSame('application/json', $response->headers['Content-Type']);
        $this->assertSame('no-store', $response->headers['Cache-Control']);
        $this->assertArrayNotHasKey('Access-Control-Allow-Origin', $response->headers);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function corruptStateProvider(): array
    {
        return [
            'empty' => [''],
            'garbage' => ['{not json'],
            'json scalar' => ['42'],
            'json string' => ['"x"'],
            'truncated' => ['{"v":3,"status":"ok","err'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('corruptStateProvider')]
    public function testCorruptStateFileFallsBackToDefaults(string $contents): void
    {
        file_put_contents($this->stateFile, $contents);

        $response = $this->get();

        $this->assertSame(200, $response->status);
        $this->assertSame(['v' => 0, 'status' => 'ok', 'error' => ''], $this->json($response));
    }

    public function testWronglyTypedFieldsAreIgnoredIndividually(): void
    {
        file_put_contents($this->stateFile, '{"v":"9","status":"exploded","error":["x"]}');

        $this->assertSame(['v' => 0, 'status' => 'ok', 'error' => ''], $this->json($this->get()));
    }

    public function testExtraFieldsInTheStateFileAreNotLeaked(): void
    {
        file_put_contents($this->stateFile, '{"v":2,"status":"ok","error":"","secret":"TOKEN","env":{"A":"B"}}');

        $this->assertStringNotContainsString('TOKEN', $this->get()->body);
    }

    public function testFailedStateReportsError(): void
    {
        file_put_contents($this->stateFile, '{"v":2,"status":"failed","error":"Boom"}');

        $this->assertSame(['v' => 2, 'status' => 'failed', 'error' => 'Boom'], $this->json($this->get()));
    }

    /**
     * @return list<array{0: string}>
     */
    public static function nonGetProvider(): array
    {
        return [['POST'], ['PUT'], ['DELETE'], ['HEAD'], ['OPTIONS'], ['PATCH']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('nonGetProvider')]
    public function testOnlyGetIsAllowed(string $method): void
    {
        $response = $this->get('localhost', $method);

        $this->assertSame(405, $response->status);
        $this->assertSame('GET', $response->headers['Allow']);
        $this->assertStringNotContainsString('"status"', $response->body);
    }

    public function testUnknownEndpointUnderPrefixIs404(): void
    {
        foreach (['/__staticforge/build', '/__staticforge/', '/__staticforge/state/x', '/__staticforge'] as $uri) {
            $response = $this->router()->handle('GET', $uri, 'localhost');

            $this->assertNotNull($response, $uri);
            $this->assertSame(404, $response->status, $uri);
        }
    }

    public function testEncodedPrefixCannotBypassTheHostCheck(): void
    {
        $response = $this->router()->handle('GET', '/%5F%5Fstaticforge/state', 'evil.com');

        $this->assertNotNull($response);
        $this->assertSame(403, $response->status);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function foreignHostProvider(): array
    {
        return [
            'other site' => ['evil.com'],
            'other site with port' => ['evil.com:8000'],
            'rebinding prefix' => ['localhost.evil.com'],
            'rebinding ip prefix' => ['127.0.0.1.evil.com'],
            'rebinding suffix' => ['evil-localhost'],
            'lan ip' => ['192.168.1.10:8000'],
            'ipv6 other' => ['[::2]'],
            'empty' => [''],
            'whitespace' => ['   '],
            'trailing dot' => ['localhost.'],
            'userinfo trick' => ['localhost@evil.com'],
            'unterminated bracket' => ['[::1'],
            'port only' => [':8000'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('foreignHostProvider')]
    public function testForeignHostGets403WithoutStateData(string $host): void
    {
        file_put_contents($this->stateFile, '{"v":5,"status":"failed","error":"/srv/site/secret"}');

        $response = $this->get($host);

        $this->assertSame(403, $response->status);
        $this->assertStringNotContainsString('secret', $response->body);
        $this->assertStringNotContainsString('"v"', $response->body);
    }

    public function testHostCheckHappensBeforeMethodCheck(): void
    {
        $this->assertSame(403, $this->get('evil.com', 'POST')->status);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function allowedHostProvider(): array
    {
        return [
            'localhost' => ['localhost'],
            'localhost port' => ['localhost:8000'],
            'localhost upper' => ['LOCALHOST:8000'],
            'ipv4' => ['127.0.0.1'],
            'ipv4 port' => ['127.0.0.1:8123'],
            'ipv6' => ['[::1]'],
            'ipv6 port' => ['[::1]:8000'],
            'bound host' => ['myhost.test:8000'],
            'bound host mixed case' => ['MyHost.Test'],
            'padded' => ['  localhost  '],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('allowedHostProvider')]
    public function testAllowedHostHeaderIsAccepted(string $host): void
    {
        $this->assertSame(200, $this->get($host)->status);
    }

    public function testNoAllowedHostsMeansEveryoneGets403(): void
    {
        $response = $this->router([])->handle('GET', '/__staticforge/state', 'localhost');

        $this->assertNotNull($response);
        $this->assertSame(403, $response->status);
    }

    public function testErrorTextIsCappedAtFiveLines(): void
    {
        $lines = [];
        for ($i = 1; $i <= 12; $i++) {
            $lines[] = 'line ' . $i;
        }
        file_put_contents($this->stateFile, (string) json_encode(['v' => 1, 'status' => 'failed', 'error' => implode("\n", $lines)]));

        $error = $this->json($this->get())['error'];

        $this->assertSame("line 1\nline 2\nline 3\nline 4\nline 5", $error);
    }

    public function testErrorTextIsCappedAtFiveHundredCharacters(): void
    {
        file_put_contents($this->stateFile, (string) json_encode(['v' => 1, 'status' => 'failed', 'error' => str_repeat('é', 2000)]));

        $error = $this->json($this->get())['error'];

        $this->assertSame(500, mb_strlen($error));
    }

    public function testAppRootIsReplacedByDotEverywhere(): void
    {
        $raw = "Error in /srv/site/content/a.md\n"
            . "at /srv/site/src/X.php:10 and /srv/site/vendor/y.php\n"
            . '/srv/site/';
        file_put_contents($this->stateFile, (string) json_encode(['v' => 1, 'status' => 'failed', 'error' => $raw]));

        $error = $this->json($this->get())['error'];

        $this->assertStringNotContainsString('/srv/site', $error);
        $this->assertStringContainsString('Error in ./content/a.md', $error);
        $this->assertStringContainsString('at ./src/X.php:10 and ./vendor/y.php', $error);
    }

    public function testAppRootWithTrailingSlashIsAlsoReplaced(): void
    {
        $router = new DevServerRouter($this->base . '/public', true, $this->stateFile, ['localhost'], '/srv/site/');
        file_put_contents($this->stateFile, (string) json_encode(['v' => 1, 'status' => 'failed', 'error' => 'x /srv/site/a']));

        $response = $router->handle('GET', '/__staticforge/state', 'localhost');

        $this->assertNotNull($response);
        $this->assertStringNotContainsString('/srv/site', $response->body);
    }

    public function testAppRootReplacementHappensBeforeTheCharacterCap(): void
    {
        $error = str_repeat('/srv/site/', 400);
        file_put_contents($this->stateFile, (string) json_encode(['v' => 1, 'status' => 'failed', 'error' => $error]));

        $reported = $this->json($this->get())['error'];

        $this->assertSame(500, mb_strlen($reported));
        $this->assertStringStartsWith('./././', $reported);
    }

    public function testStateFileThatIsADirectoryFallsBackToDefaults(): void
    {
        mkdir($this->stateFile);

        $this->assertSame(['v' => 0, 'status' => 'ok', 'error' => ''], $this->json($this->get()));
    }

    public function testRouterWithoutStateFileReportsDefaults(): void
    {
        $router = new DevServerRouter($this->base . '/public', true, null, ['localhost']);

        $response = $router->handle('GET', '/__staticforge/state', 'localhost');

        $this->assertNotNull($response);
        $this->assertSame(200, $response->status);
    }
}
