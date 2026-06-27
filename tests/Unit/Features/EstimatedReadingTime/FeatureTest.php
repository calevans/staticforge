<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\EstimatedReadingTime;

use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Features\EstimatedReadingTime\Feature;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class FeatureTest extends UnitTestCase
{
    private Feature $feature;
    private EventManager $eventManager;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventManager = new EventManager($this->container);
        $this->feature = new Feature();
        $this->feature->setContainer($this->container);
        $this->feature->register($this->eventManager);

        $this->tempDir = sys_get_temp_dir() . '/staticforge_reading_time_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    public function testRegisterRegistersEvent(): void
    {
        $listeners = $this->eventManager->getListeners('PRE_RENDER');
        $this->assertNotEmpty($listeners);
        $this->assertCount(1, $listeners);
        $this->assertEquals([$this->feature, 'handlePreRender'], $listeners[0]['callback']);
    }

    public function testHandlePreRenderReturnsContextUnchangedWhenFileMissing(): void
    {
        $context = ['file_path' => $this->tempDir . '/does-not-exist.md'];
        $result = $this->feature->handlePreRender($this->container, $context);

        $this->assertSame($context, $result);
    }

    public function testHandlePreRenderReturnsContextUnchangedWhenNoFilePath(): void
    {
        $context = [];
        $result = $this->feature->handlePreRender($this->container, $context);

        $this->assertSame($context, $result);
    }

    public function testHandlePreRenderInjectsReadingTimeIntoFileMetadata(): void
    {
        $filePath = $this->tempDir . '/post.md';
        $content = "---\ntitle: Test\n---\n" . str_repeat('word ', 400);
        file_put_contents($filePath, $content);

        $context = ['file_path' => $filePath];
        $result = $this->feature->handlePreRender($this->container, $context);

        $this->assertArrayHasKey('file_metadata', $result);
        $this->assertSame(2, $result['file_metadata']['reading_time_minutes']);
        $this->assertSame('2 min read', $result['file_metadata']['reading_time_label']);
    }

    public function testHandlePreRenderUpdatesLegacyMetadataKeyWhenPresent(): void
    {
        $filePath = $this->tempDir . '/post2.md';
        file_put_contents($filePath, str_repeat('word ', 200));

        $context = ['file_path' => $filePath, 'metadata' => ['title' => 'Existing']];
        $result = $this->feature->handlePreRender($this->container, $context);

        $this->assertArrayHasKey('reading_time_minutes', $result['metadata']);
        $this->assertSame(1, $result['metadata']['reading_time_minutes']);
    }

    public function testHandlePreRenderRespectsExcludeConfig(): void
    {
        $filePath = $this->tempDir . '/excluded-post.md';
        file_put_contents($filePath, str_repeat('word ', 400));

        $this->setContainerVariable('site_config', [
            'reading_time' => [
                'exclude' => ['excluded-post'],
            ],
        ]);

        $context = ['file_path' => $filePath];
        $result = $this->feature->handlePreRender($this->container, $context);

        $this->assertSame($context, $result);
        $this->assertArrayNotHasKey('file_metadata', $result);
    }

    public function testHandlePreRenderRespectsCustomWpmAndLabels(): void
    {
        $filePath = $this->tempDir . '/custom.md';
        file_put_contents($filePath, str_repeat('word ', 100));

        $this->setContainerVariable('site_config', [
            'reading_time' => [
                'wpm' => 100,
                'label_singular' => 'minute read',
                'label_plural' => 'minutes read',
            ],
        ]);

        $context = ['file_path' => $filePath];
        $result = $this->feature->handlePreRender($this->container, $context);

        $this->assertSame(1, $result['file_metadata']['reading_time_minutes']);
        $this->assertSame('1 minute read', $result['file_metadata']['reading_time_label']);
    }
}
