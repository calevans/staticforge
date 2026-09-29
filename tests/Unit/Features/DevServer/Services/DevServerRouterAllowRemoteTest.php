<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\DevServer\Services;

use EICC\StaticForge\Features\DevServer\Services\DevServerRouter;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Host header policy of /__staticforge/state with and without --allow-remote.
 */
class DevServerRouterAllowRemoteTest extends UnitTestCase
{
    use SymlinkSafeCleanup;

    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = (string) realpath(sys_get_temp_dir()) . '/sf_remote_' . bin2hex(random_bytes(4));
        mkdir($this->base . '/public', 0755, true);
        file_put_contents($this->base . '/state.json', '{"v":2,"status":"ok","error":""}');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->base);
        parent::tearDown();
    }

    private function statusFor(string $host, bool $allowRemote): int
    {
        $router = new DevServerRouter(
            $this->base . '/public',
            true,
            $this->base . '/state.json',
            ['localhost', '127.0.0.1', '[::1]'],
            '/srv/site',
            $allowRemote
        );
        $response = $router->handle('GET', '/__staticforge/state', $host);
        $this->assertNotNull($response);

        return $response->status;
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function ipLiteralHostProvider(): array
    {
        return [
            'ipv4' => ['192.168.1.5'],
            'ipv4 with port' => ['192.168.1.5:8000'],
            'ipv4 upper edge' => ['255.255.255.255:1'],
            'bracketed ipv6' => ['[fe80::1]'],
            'bracketed ipv6 with port' => ['[fe80::1]:8000'],
            'bracketed full ipv6' => ['[2001:db8:0:0:0:0:0:1]:80'],
            'bare ipv6' => ['fe80::1'],
            'bare compressed ipv6' => ['2001:db8::1'],
            'ipv4 mapped ipv6 bracketed' => ['[::ffff:192.168.1.5]:80'],
            'surrounding whitespace' => ['  192.168.1.5:8000 '],
        ];
    }

    #[DataProvider('ipLiteralHostProvider')]
    public function testIpLiteralHostsAreAcceptedWithAllowRemote(string $host): void
    {
        $this->assertSame(200, $this->statusFor($host, true));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonLiteralHostProvider(): array
    {
        return [
            'hostname' => ['example.com'],
            'hostname with port' => ['example.com:8000'],
            'lookalike of localhost' => ['localhost.evil.example'],
            'ip as subdomain' => ['192.168.1.5.evil.example'],
            'trailing dot ip' => ['192.168.1.5.'],
            'trailing dot ip with port' => ['192.168.1.5.:8000'],
            'userinfo before ipv4' => ['user@192.168.1.5'],
            'userinfo with port' => ['user@192.168.1.5:8000'],
            'ip as userinfo of hostname' => ['192.168.1.5@evil.example'],
            'ip as userinfo with port' => ['192.168.1.5:80@evil.example'],
            'octet out of range' => ['999.1.1.1'],
            'too few octets' => ['192.168.1'],
            'bracketed hostname' => ['[evil.example]'],
            'bracketed ipv6 with trailing dot' => ['[fe80::1].'],
            'bracketed ipv6 with userinfo' => ['user@[fe80::1]'],
            'bracketed ipv6 with junk port' => ['[fe80::1]:abc'],
            'bare ipv6 with zone' => ['fe80::1%eth0'],
            'empty host' => [''],
            'ipv4 followed by path' => ['192.168.1.5/evil'],
        ];
    }

    #[DataProvider('nonLiteralHostProvider')]
    public function testHostnamesAndIpTricksAreStillForbiddenWithAllowRemote(string $host): void
    {
        $this->assertSame(403, $this->statusFor($host, true));
    }

    #[DataProvider('ipLiteralHostProvider')]
    public function testUnlistedIpLiteralsAreForbiddenWithoutAllowRemote(string $host): void
    {
        $this->assertSame(403, $this->statusFor($host, false));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function boundHostProvider(): array
    {
        return [
            'localhost' => ['localhost'],
            'localhost with port' => ['localhost:8000'],
            'loopback ipv4' => ['127.0.0.1:8000'],
            'loopback ipv6' => ['[::1]:8000'],
            'uppercase localhost' => ['LOCALHOST:8000'],
        ];
    }

    #[DataProvider('boundHostProvider')]
    public function testBoundAndLoopbackHostsWorkWithAndWithoutAllowRemote(string $host): void
    {
        $this->assertSame(200, $this->statusFor($host, false));
        $this->assertSame(200, $this->statusFor($host, true));
    }

    public function testAllowRemoteDoesNotWidenTheHostnameAllowlist(): void
    {
        $this->assertSame(403, $this->statusFor('evil.example', true));
        $this->assertSame(200, $this->statusFor('localhost', true));
    }

    public function testAllowRemoteDoesNotTurnOnTheEndpointOutsideWatchMode(): void
    {
        $router = new DevServerRouter($this->base . '/public', false, $this->base . '/state.json', [], '', true);

        $response = $router->handle('GET', '/__staticforge/state', '192.168.1.5');

        $this->assertNotNull($response);
        $this->assertSame(404, $response->status);
        $this->assertStringNotContainsString('"v"', $response->body);
    }

    public function testAllowRemoteStillEnforcesTheMethodAndPathAfterTheHostCheck(): void
    {
        $router = new DevServerRouter($this->base . '/public', true, $this->base . '/state.json', [], '', true);

        $post = $router->handle('POST', '/__staticforge/state', '192.168.1.5');
        $other = $router->handle('GET', '/__staticforge/other', '192.168.1.5');

        $this->assertNotNull($post);
        $this->assertNotNull($other);
        $this->assertSame(405, $post->status);
        $this->assertSame(404, $other->status);
    }
}
