<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\DevServer\Services;

use EICC\StaticForge\Features\DevServer\Services\HostDecision;
use EICC\StaticForge\Features\DevServer\Services\HostPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HostPolicyTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: bool, 2: bool, 3: HostDecision}>
     */
    public static function decisionProvider(): array
    {
        $rows = [];
        foreach (['127.0.0.1', 'localhost', '::1', '[::1]', 'LOCALHOST'] as $host) {
            foreach ([false, true] as $remote) {
                foreach ([false, true] as $lando) {
                    $rows["$host remote=" . (int) $remote . ' lando=' . (int) $lando] =
                        [$host, $remote, $lando, HostDecision::Run];
                }
            }
        }
        foreach (['0.0.0.0', '192.168.1.10', '::', '127.0.0.2', 'example.test'] as $host) {
            $rows["$host plain"] = [$host, false, false, HostDecision::Refuse];
            $rows["$host lando"] = [$host, false, true, HostDecision::WarnLando];
            $rows["$host remote"] = [$host, true, false, HostDecision::WarnRemote];
            $rows["$host remote lando"] = [$host, true, true, HostDecision::WarnRemote];
        }

        return $rows;
    }

    #[DataProvider('decisionProvider')]
    public function testDecisionTable(string $host, bool $allowRemote, bool $lando, HostDecision $expected): void
    {
        $this->assertSame($expected, HostPolicy::decide($host, $allowRemote, $lando));
    }

    public function testAllowedHostsAlwaysContainsLoopbackNames(): void
    {
        $hosts = HostPolicy::allowedHosts('localhost', false, null);

        $this->assertEqualsCanonicalizing(['localhost', '127.0.0.1', '[::1]'], $hosts);
    }

    public function testAllowedHostsIncludesTheBoundHostLowercased(): void
    {
        $this->assertContains('my-box.test', HostPolicy::allowedHosts('My-Box.Test', false, null));
        $this->assertContains('0.0.0.0', HostPolicy::allowedHosts('0.0.0.0', false, null));
    }

    public function testBareIpv6BoundHostIsBracketed(): void
    {
        $hosts = HostPolicy::allowedHosts('::1', false, null);

        $this->assertContains('[::1]', $hosts);
        $this->assertNotContains('::1', $hosts);
        $this->assertContains('[fe80::1]', HostPolicy::allowedHosts('fe80::1', false, null));
    }

    public function testLandoHostsAreReadFromLandoInfoOnlyUnderLando(): void
    {
        $info = (string) json_encode([
            'appserver' => ['urls' => ['https://Static-Forge.lndo.site/', 'http://static-forge.lndo.site/', 'http://localhost:32771']],
            'database' => ['urls' => []],
            'other' => ['urls' => ['http://other.lndo.site:8080/path']],
        ]);

        $under = HostPolicy::allowedHosts('0.0.0.0', true, $info);
        $notUnder = HostPolicy::allowedHosts('0.0.0.0', false, $info);

        $this->assertContains('static-forge.lndo.site', $under);
        $this->assertContains('other.lndo.site', $under);
        $this->assertSame(count($under), count(array_unique($under)));
        $this->assertNotContains('static-forge.lndo.site', $notUnder);
    }

    /**
     * @return array<string, array{0: ?string}>
     */
    public static function unusableLandoInfoProvider(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'not json' => ['{oops'],
            'scalar' => ['5'],
            'service not array' => ['{"a":"x"}'],
            'urls not array' => ['{"a":{"urls":"http://x.test"}}'],
            'url not string' => ['{"a":{"urls":[5,null,["http://x.test"]]}}'],
            'url without host' => ['{"a":{"urls":["/just/a/path","::::"]}}'],
        ];
    }

    #[DataProvider('unusableLandoInfoProvider')]
    public function testUnusableLandoInfoAddsNothingAndDoesNotThrow(?string $info): void
    {
        $hosts = HostPolicy::allowedHosts('localhost', true, $info);

        $this->assertEqualsCanonicalizing(['localhost', '127.0.0.1', '[::1]'], $hosts);
    }
}
