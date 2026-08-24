<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Core;

use EICC\StaticForge\Core\Frontmatter;
use PHPUnit\Framework\TestCase;

class FrontmatterTest extends TestCase
{
    public function testStripsContentKey(): void
    {
        $result = Frontmatter::stripReserved(['title' => 'Real Title', 'content' => 'Forged content']);

        $this->assertArrayNotHasKey('content', $result);
        $this->assertSame('Real Title', $result['title']);
    }

    public function testStripsAppRootKey(): void
    {
        $result = Frontmatter::stripReserved(['title' => 'T', 'app_root' => '/etc']);

        $this->assertArrayNotHasKey('app_root', $result);
    }

    public function testStripsPathDirectoryKeys(): void
    {
        $result = Frontmatter::stripReserved([
            'title' => 'T',
            'OUTPUT_DIR' => '/tmp/evil',
            'SOURCE_DIR' => '/tmp/evil',
            'TEMPLATE_DIR' => '/tmp/evil',
            'FEATURES_DIR' => '/tmp/evil',
            'source_file' => '/tmp/evil',
        ]);

        $this->assertSame(['title' => 'T'], $result);
    }

    public function testStripsCredentialShapedKeys(): void
    {
        $result = Frontmatter::stripReserved([
            'title' => 'T',
            'SFTP_PASSWORD' => 'leaked',
            'API_SECRET' => 'leaked',
            'AUTH_TOKEN' => 'leaked',
            'SFTP_PRIVATE_KEY' => 'leaked',
        ]);

        $this->assertSame(['title' => 'T'], $result);
    }

    public function testKeepsOrdinaryPageFields(): void
    {
        $metadata = [
            'title' => 'My Post',
            'tags' => ['php', 'staticforge'],
            'category' => 'Tutorials',
            'custom_field' => 'anything the author wants',
        ];

        $this->assertSame($metadata, Frontmatter::stripReserved($metadata));
    }
}
