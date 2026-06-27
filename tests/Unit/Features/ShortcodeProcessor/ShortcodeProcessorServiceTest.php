<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\ShortcodeProcessor;

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
        $this->service = new ShortcodeProcessorService($logger, $this->shortcodeManager);
    }

    public function testRegisterReferenceShortcodes(): void
    {
        $this->shortcodeManager->expects($this->exactly(3))
            ->method('register');

        $this->service->registerReferenceShortcodes();
    }

    public function testProcessShortcodesIgnoresNonMdFiles(): void
    {
        $parameters = ['file_path' => 'test.html'];
        $result = $this->service->processShortcodes($this->container, $parameters);

        $this->assertSame($parameters, $result);
    }

    public function testProcessShortcodesProcessesMdFiles(): void
    {
        $parameters = [
            'file_path' => 'test.md',
            'file_content' => 'content with [shortcode]'
        ];

        $this->shortcodeManager->expects($this->once())
            ->method('process')
            ->with('content with [shortcode]')
            ->willReturn('processed content');

        $result = $this->service->processShortcodes($this->container, $parameters);

        $this->assertEquals('processed content', $result['file_content']);
    }

    public function testSplitFrontmatter(): void
    {
        $method = new ReflectionMethod(ShortcodeProcessorService::class, 'splitFrontmatter');
        $method->setAccessible(true);

        $content = "---\ntitle: Test\n---\nBody content";
        $result = $method->invoke($this->service, $content);

        $this->assertEquals("---\ntitle: Test\n---\n", $result['frontmatter']);
        $this->assertEquals("Body content", $result['body']);
    }

    public function testSplitFrontmatterNoFrontmatter(): void
    {
        $method = new ReflectionMethod(ShortcodeProcessorService::class, 'splitFrontmatter');
        $method->setAccessible(true);

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

        $parameters = ['file_path' => $tempFile];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SOURCE_DIR not set in container');

        try {
            $this->service->processShortcodes($this->container, $parameters);
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

        $parameters = ['file_path' => $outsideFile];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Security Error/');

        try {
            $this->service->processShortcodes($this->container, $parameters);
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

        $parameters = ['file_path' => $filePath];

        try {
            if (function_exists('posix_getuid') && posix_getuid() === 0) {
                $this->markTestSkipped('Cannot test unreadable files as root');
            }
            $result = $this->service->processShortcodes($this->container, $parameters);
            $this->assertSame($parameters, $result);
        } finally {
            chmod($filePath, 0644);
            unlink($filePath);
            rmdir($sourceDir);
        }
    }
}
