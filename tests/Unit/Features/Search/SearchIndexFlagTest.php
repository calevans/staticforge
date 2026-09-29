<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\Search;

use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Core\OutputWriter;
use EICC\StaticForge\Features\Search\Services\SearchIndexService;
use EICC\Utils\Container;
use EICC\Utils\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SearchIndexFlagTest extends TestCase
{
    private SearchIndexService $service;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/staticforge_search_flag_' . uniqid('', true);
        mkdir($this->tempDir);

        $container = new Container();
        $container->setVariable('OUTPUT_DIR', $this->tempDir);
        $container->setVariable('SITE_BASE_URL', 'https://example.com');
        $container->setVariable('site_config', []);

        $logger = $this->createMock(Log::class);
        $this->service = new SearchIndexService($logger, new OutputWriter($container, $logger), $container);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tempDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->tempDir);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function indexedCount(array $metadata): int
    {
        $this->service->collectPage(new RenderEvent(
            name: 'POST_RENDER',
            filePath: '',
            fileUrl: '',
            metadata: array_merge(['title' => 'Page'], $metadata),
            renderedContent: '<p>Body text</p>',
            outputPath: $this->tempDir . '/page.html',
        ));
        $this->service->buildIndex();

        $json = json_decode((string)file_get_contents($this->tempDir . '/search.json'), true);
        $this->assertIsArray($json);

        return count($json);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function excludedProvider(): array
    {
        return [
            'bool false' => [false],
            'string false' => ['false'],
            'string no' => ['no'],
        ];
    }

    #[DataProvider('excludedProvider')]
    public function testPageIsExcludedWhenSearchIndexIsFalsy(mixed $value): void
    {
        $this->assertSame(0, $this->indexedCount(['search_index' => $value]));
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function includedProvider(): array
    {
        return [
            'absent' => [[]],
            'bool true' => [['search_index' => true]],
            'string true' => [['search_index' => 'true']],
            'null' => [['search_index' => null]],
            'garbage' => [['search_index' => 'garbage']],
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     */
    #[DataProvider('includedProvider')]
    public function testPageIsIndexedWhenSearchIndexIsNotFalse(array $metadata): void
    {
        $this->assertSame(1, $this->indexedCount($metadata));
    }

    public function testNoindexAloneDoesNotRemovePageFromSearch(): void
    {
        $this->assertSame(1, $this->indexedCount(['noindex' => true]));
    }
}
