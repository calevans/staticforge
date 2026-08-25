<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\ShortcodeProcessor;

use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Features\ShortcodeProcessor\Services\ShortcodeProcessorService;
use EICC\StaticForge\Shortcodes\ShortcodeManager;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\Utils\Log;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionMethod;

class ShortcodeProcessorServiceTest extends UnitTestCase
{
    private ShortcodeProcessorService $service;
    private ShortcodeManager&MockObject $shortcodeManager;

    protected function setUp(): void
    {
        parent::setUp();
        $logger = $this->createMock(Log::class);
        $this->shortcodeManager = $this->createMock(ShortcodeManager::class);
        $this->service = new ShortcodeProcessorService($logger, $this->shortcodeManager, $this->container);
    }

    private function makeEvent(string $filePath, string $fileContent = ''): RenderEvent
    {
        return new RenderEvent(
            name: 'PRE_RENDER',
            filePath: $filePath,
            fileUrl: '',
            metadata: [],
            extra: $fileContent !== '' ? ['file_content' => $fileContent] : [],
        );
    }

    public function testRegisterReferenceShortcodes(): void
    {
        $this->shortcodeManager->expects($this->exactly(3))
            ->method('register');

        $this->service->registerReferenceShortcodes();
    }

    public function testProcessShortcodesIgnoresNonMdFiles(): void
    {
        $event = $this->makeEvent('test.html');

        $this->service->processShortcodes($event);

        $this->assertSame([], $event->extra);
    }

    public function testProcessShortcodesProcessesMdFiles(): void
    {
        $event = $this->makeEvent('test.md', 'content with [shortcode]');

        $this->shortcodeManager->expects($this->once())
            ->method('process')
            ->with('content with [shortcode]')
            ->willReturn('processed content');

        $this->service->processShortcodes($event);

        $this->assertEquals('processed content', $event->extra['file_content']);
    }

    public function testSplitFrontmatter(): void
    {
        $method = new ReflectionMethod(ShortcodeProcessorService::class, 'splitFrontmatter');

        $content = "---\ntitle: Test\n---\nBody content";
        $result = $method->invoke($this->service, $content);

        $this->assertEquals("---\ntitle: Test\n---\n", $result['frontmatter']);
        $this->assertEquals("Body content", $result['body']);
    }

    public function testSplitFrontmatterNoFrontmatter(): void
    {
        $method = new ReflectionMethod(ShortcodeProcessorService::class, 'splitFrontmatter');

        $content = "Body content only";
        $result = $method->invoke($this->service, $content);

        $this->assertEquals("", $result['frontmatter']);
        $this->assertEquals("Body content only", $result['body']);
    }

    public function testProcessShortcodesThrowsWhenSourceDirNotSet(): void
    {
        $this->container->updateVariable('SOURCE_DIR', null);

        $tempFile = sys_get_temp_dir() . '/staticforge_sp_test_' . uniqid() . '.md';
        file_put_contents($tempFile, 'content');

        $event = $this->makeEvent($tempFile);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SOURCE_DIR not set in container');

        try {
            $this->service->processShortcodes($event);
        } finally {
            unlink($tempFile);
        }
    }

    public function testProcessShortcodesThrowsWhenFileOutsideSourceDir(): void
    {
        $sourceDir = sys_get_temp_dir() . '/staticforge_sp_source_' . uniqid();
        mkdir($sourceDir, 0755, true);
        $this->setContainerVariable('SOURCE_DIR', $sourceDir);

        $outsideFile = sys_get_temp_dir() . '/staticforge_sp_outside_' . uniqid() . '.md';
        file_put_contents($outsideFile, 'content');

        $event = $this->makeEvent($outsideFile);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Security Error/');

        try {
            $this->service->processShortcodes($event);
        } finally {
            unlink($outsideFile);
            rmdir($sourceDir);
        }
    }

    public function testProcessShortcodesReturnsParametersWhenFileUnreadable(): void
    {
        $sourceDir = sys_get_temp_dir() . '/staticforge_sp_source_' . uniqid();
        mkdir($sourceDir, 0755, true);
        $this->setContainerVariable('SOURCE_DIR', $sourceDir);

        $filePath = $sourceDir . '/unreadable.md';
        file_put_contents($filePath, 'content');
        chmod($filePath, 0000);

        $event = $this->makeEvent($filePath);

        try {
            if (function_exists('posix_getuid') && posix_getuid() === 0) {
                $this->markTestSkipped('Cannot test unreadable files as root');
            }
            $this->service->processShortcodes($event);
            $this->assertSame([], $event->extra);
        } finally {
            chmod($filePath, 0644);
            unlink($filePath);
            rmdir($sourceDir);
        }
    }
}
