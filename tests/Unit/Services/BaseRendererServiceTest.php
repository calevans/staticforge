<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Services;

use EICC\StaticForge\Services\BaseRendererService;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\Utils\Log;
use PHPUnit\Framework\MockObject\MockObject;

class BaseRendererServiceTest extends UnitTestCase
{
    private BaseRendererService $service;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var Log&MockObject $logger */
        $logger = $this->createMock(Log::class);
        $this->service = new class($logger) extends BaseRendererService {
        };
    }

    public function testApplyDefaultMetadataFillsInMissingDefaults(): void
    {
        $result = $this->service->applyDefaultMetadata([]);

        $this->assertSame('base', $result['template']);
        $this->assertSame('Untitled Page', $result['title']);
    }

    public function testApplyDefaultMetadataDoesNotOverrideExistingValues(): void
    {
        $result = $this->service->applyDefaultMetadata(['template' => 'custom', 'title' => 'My Page']);

        $this->assertSame('custom', $result['template']);
        $this->assertSame('My Page', $result['title']);
    }

    public function testBeautifyHtmlIndentsValidHtml(): void
    {
        $html = '<html><body><p>Hello</p></body></html>';

        $result = $this->service->beautifyHtml($html);

        $this->assertStringContainsString('<html>', $result);
        $this->assertStringContainsString('Hello', $result);
    }

    public function testBeautifyHtmlProtectsPreBlocksFromCollapsing(): void
    {
        $html = '<html><body><pre>  preformatted   text  </pre></body></html>';

        $result = $this->service->beautifyHtml($html);

        $this->assertStringContainsString('  preformatted   text  ', $result);
    }

    public function testBeautifyHtmlReturnsOriginalOnMalformedInput(): void
    {
        // Invalid UTF-8 byte sequences can cause preg_replace_callback to fail and return null;
        // the service should gracefully fall back to the original content rather than erroring.
        $invalidUtf8Html = "<html><body>\xB1\x31</body></html>";

        $result = $this->service->beautifyHtml($invalidUtf8Html);

        $this->assertSame($invalidUtf8Html, $result);
    }

    public function testGenerateOutputPathPreservesRelativeStructure(): void
    {
        $this->setContainerVariable('SOURCE_DIR', '/content');
        $this->setContainerVariable('OUTPUT_DIR', '/public');

        $result = $this->service->generateOutputPath('/content/blog/post.md', $this->container);

        $this->assertSame('/public' . DIRECTORY_SEPARATOR . 'blog/post.md', $result);
    }

    public function testGenerateOutputPathReplacesExtensionWhenRequested(): void
    {
        $this->setContainerVariable('SOURCE_DIR', '/content');
        $this->setContainerVariable('OUTPUT_DIR', '/public');

        $result = $this->service->generateOutputPath('/content/blog/post.md', $this->container, 'html');

        $this->assertSame('/public' . DIRECTORY_SEPARATOR . 'blog/post.html', $result);
    }

    public function testGenerateOutputPathFallsBackToFilenameWhenOutsideSourceDir(): void
    {
        $this->setContainerVariable('SOURCE_DIR', '/content');
        $this->setContainerVariable('OUTPUT_DIR', '/public');

        $result = $this->service->generateOutputPath('/elsewhere/post.md', $this->container);

        $this->assertSame('/public' . DIRECTORY_SEPARATOR . 'post.md', $result);
    }

    public function testGenerateOutputPathThrowsWhenSourceDirMissing(): void
    {
        $this->setContainerVariable('SOURCE_DIR', '');
        $this->setContainerVariable('OUTPUT_DIR', '/public');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SOURCE_DIR not set in container');

        $this->service->generateOutputPath('/content/post.md', $this->container);
    }

    public function testGenerateOutputPathThrowsWhenOutputDirMissing(): void
    {
        $this->setContainerVariable('SOURCE_DIR', '/content');
        $this->setContainerVariable('OUTPUT_DIR', '');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OUTPUT_DIR not set in container');

        $this->service->generateOutputPath('/content/post.md', $this->container);
    }
}
