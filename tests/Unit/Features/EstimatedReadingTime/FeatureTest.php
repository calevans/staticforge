<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\EstimatedReadingTime;

use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\FeatureFactory;
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

        $this->eventManager = new EventManager();
        $feature = (new FeatureFactory($this->container))->make(Feature::class);
        $this->assertInstanceOf(Feature::class, $feature);
        $this->feature = $feature;
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

    /**
     * @param array<string, mixed> $metadata
     */
    private function makeEvent(string $filePath, array $metadata = []): RenderEvent
    {
        return new RenderEvent(
            name: 'PRE_RENDER',
            filePath: $filePath,
            fileUrl: '',
            metadata: $metadata,
        );
    }

    public function testHandlePreRenderReturnsContextUnchangedWhenFileMissing(): void
    {
        $event = $this->makeEvent($this->tempDir . '/does-not-exist.md');
        $this->feature->handlePreRender($event);

        $this->assertSame([], $event->metadata);
    }

    public function testHandlePreRenderReturnsContextUnchangedWhenNoFilePath(): void
    {
        $event = $this->makeEvent('');
        $this->feature->handlePreRender($event);

        $this->assertSame([], $event->metadata);
    }

    public function testHandlePreRenderInjectsReadingTimeIntoFileMetadata(): void
    {
        $filePath = $this->tempDir . '/post.md';
        $content = "---\ntitle: Test\n---\n" . str_repeat('word ', 400);
        file_put_contents($filePath, $content);

        $event = $this->makeEvent($filePath);
        $this->feature->handlePreRender($event);

        $this->assertSame(2, $event->metadata['reading_time_minutes']);
        $this->assertSame('2 min read', $event->metadata['reading_time_label']);
    }

    public function testHandlePreRenderUpdatesLegacyMetadataKeyWhenPresent(): void
    {
        $filePath = $this->tempDir . '/post2.md';
        file_put_contents($filePath, str_repeat('word ', 200));

        $event = $this->makeEvent($filePath, ['title' => 'Existing']);
        $this->feature->handlePreRender($event);

        $this->assertArrayHasKey('reading_time_minutes', $event->metadata);
        $this->assertSame(1, $event->metadata['reading_time_minutes']);
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

        $event = $this->makeEvent($filePath);
        $this->feature->handlePreRender($event);

        $this->assertSame([], $event->metadata);
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

        $event = $this->makeEvent($filePath);
        $this->feature->handlePreRender($event);

        $this->assertSame(1, $event->metadata['reading_time_minutes']);
        $this->assertSame('1 minute read', $event->metadata['reading_time_label']);
    }
}
