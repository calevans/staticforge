<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\ShortcodeProcessor;

use EICC\StaticForge\Features\ShortcodeProcessor\Services\ShortcodeProcessorService;
use EICC\StaticForge\Shortcodes\ShortcodeManager;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\Utils\Log;
use ReflectionMethod;

class ShortcodeProcessorServiceTest extends UnitTestCase
{
    private ShortcodeProcessorService $service;
    private ShortcodeManager $shortcodeManager;

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
}
