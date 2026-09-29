<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\RobotsTxt;

use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\OutputWriter;
use EICC\StaticForge\Features\RobotsTxt\Services\RobotsTxtGenerator;
use EICC\StaticForge\Features\RobotsTxt\Services\RobotsTxtService;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class RobotsTxtFlagTest extends UnitTestCase
{
    private string $outputDir;
    private string $sourceDir;

    protected function setUp(): void
    {
        parent::setUp();
        $base = sys_get_temp_dir() . '/staticforge_robots_flag_' . uniqid('', true);
        $this->sourceDir = $base . '/content';
        $this->outputDir = $base . '/public';
        mkdir($this->sourceDir . '/docs/x', 0755, true);
        mkdir($this->outputDir, 0755, true);

        $this->setContainerVariable('SOURCE_DIR', $this->sourceDir);
        $this->setContainerVariable('OUTPUT_DIR', $this->outputDir);
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.com');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory(dirname($this->sourceDir));
        parent::tearDown();
    }

    /**
     * @param array<int, array{path: string, url: string, metadata: array<string, mixed>}> $files
     * @return array<int, string>
     */
    private function disallowLines(array $files): array
    {
        $this->setContainerVariable('discovered_files', $files);
        $service = new RobotsTxtService(
            $this->container->get('logger'),
            new RobotsTxtGenerator(),
            $this->container->get(OutputWriter::class),
            new EventManager(),
            $this->container
        );
        $service->scanForRobotsMetadata();
        $service->generateRobotsTxt();

        $content = file_get_contents($this->outputDir . '/robots.txt');
        $this->assertNotFalse($content);

        return array_values(array_filter(
            array_map('trim', explode("\n", $content)),
            static fn (string $l): bool => str_starts_with($l, 'Disallow:')
        ));
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function blockingProvider(): array
    {
        return [
            'string no' => ['no'],
            'bool false' => [false],
            'padded upper' => [' NO '],
        ];
    }

    #[DataProvider('blockingProvider')]
    public function testPageWithBlockingRobotsValueIsDisallowed(mixed $robots): void
    {
        $lines = $this->disallowLines([
            ['path' => $this->sourceDir . '/secret.md', 'url' => 'secret.md', 'metadata' => ['robots' => $robots]],
        ]);

        $this->assertContains('Disallow: /secret.html', $lines);
    }

    #[DataProvider('blockingProvider')]
    public function testCategoryDefinitionWithBlockingRobotsValueDisallowsCategoryDirectory(mixed $robots): void
    {
        $lines = $this->disallowLines([
            [
                'path' => $this->sourceDir . '/private.md',
                'url' => 'private.md',
                'metadata' => ['type' => 'category', 'category' => 'private', 'robots' => $robots],
            ],
        ]);

        $this->assertContains('Disallow: /private/', $lines);
    }

    public function testNonBlockingValuesProduceNoDisallow(): void
    {
        $lines = $this->disallowLines([
            ['path' => $this->sourceDir . '/a.md', 'url' => 'a.md', 'metadata' => ['robots' => 'yes']],
            ['path' => $this->sourceDir . '/b.md', 'url' => 'b.md', 'metadata' => ['robots' => 'false']],
            ['path' => $this->sourceDir . '/c.md', 'url' => 'c.md', 'metadata' => ['robots' => '']],
            ['path' => $this->sourceDir . '/d.md', 'url' => 'd.md', 'metadata' => ['noindex' => true]],
        ]);

        $this->assertSame([], array_values(array_diff($lines, ['Disallow:'])));
    }

    public function testBlockedNestedIndexDisallowsBothFileAndDirectoryForm(): void
    {
        $lines = $this->disallowLines([
            [
                'path' => $this->sourceDir . '/docs/x/index.md',
                'url' => 'docs/x/index.md',
                'metadata' => ['robots' => 'no'],
            ],
        ]);

        $this->assertContains('Disallow: /docs/x/index.html', $lines);
        $this->assertContains('Disallow: /docs/x/$', $lines);
        $this->assertNotContains('Disallow: /docs/x/', $lines);
    }

    public function testBlockedRootIndexNeverDisallowsWholeSite(): void
    {
        $lines = $this->disallowLines([
            ['path' => $this->sourceDir . '/index.md', 'url' => 'index.md', 'metadata' => ['robots' => 'no']],
        ]);

        $this->assertContains('Disallow: /index.html', $lines);
        $this->assertNotContains('Disallow: /', $lines);
        $this->assertNotContains('Disallow:', $lines);
    }
}
