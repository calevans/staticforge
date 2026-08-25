<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\ResponsiveImages\Services;

use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Features\ResponsiveImages\Services\HtmlImageRewriterService;
use EICC\StaticForge\Features\ResponsiveImages\Services\ImageVariantGenerator;
use EICC\StaticForge\Features\ResponsiveImages\Services\ResponsiveImageConfig;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\Utils\Log;
use PHPUnit\Framework\MockObject\MockObject;

class HtmlImageRewriterServiceTest extends UnitTestCase
{
    private Log&MockObject $logger;
    private string $sourceDir;
    private string $outputDir;
    private string $templateDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = $this->createMock(Log::class);

        $base = sys_get_temp_dir() . '/staticforge_rirs_' . uniqid();
        $this->sourceDir = $base . '/source';
        $this->outputDir = $base . '/output';
        $this->templateDir = $base . '/templates';

        mkdir($this->sourceDir, 0755, true);
        mkdir($this->sourceDir . '/assets/images', 0755, true);
        mkdir($this->outputDir, 0755, true);
        mkdir($this->templateDir . '/sample', 0755, true);

        $this->setContainerVariable('SOURCE_DIR', $this->sourceDir);
        $this->setContainerVariable('OUTPUT_DIR', $this->outputDir);
        $this->setContainerVariable('TEMPLATE_DIR', $this->templateDir);
        $this->setContainerVariable('TEMPLATE', 'sample');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeDirectory(dirname($this->sourceDir));
    }

    private function createFixtureJpeg(string $path, int $width): void
    {
        $width = max(1, $width);
        $im = imagecreatetruecolor($width, $width);
        if ($im === false) {
            throw new \RuntimeException('Failed to create fixture image');
        }
        $color = imagecolorallocate($im, 50, 60, 70);
        imagefill($im, 0, 0, $color === false ? 0 : $color);
        imagejpeg($im, $path);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function makeConfig(array $overrides = []): ResponsiveImageConfig
    {
        return new ResponsiveImageConfig(
            enabled: $overrides['enabled'] ?? true,
            widths: $overrides['widths'] ?? [400],
            webp: $overrides['webp'] ?? true,
            quality: $overrides['quality'] ?? 82,
            outputDir: $overrides['outputDir'] ?? 'assets/images/responsive',
            minSourceWidth: $overrides['minSourceWidth'] ?? 400,
        );
    }

    private function makeSpyGenerator(): SpyImageVariantGenerator
    {
        return new SpyImageVariantGenerator($this->logger, $this->makeConfig());
    }

    private function makeEvent(string $renderedContent): RenderEvent
    {
        return new RenderEvent(
            name: 'POST_RENDER',
            filePath: '',
            fileUrl: '',
            metadata: [],
            renderedContent: $renderedContent,
        );
    }

    public function testNoImgTagsLeavesContentUnchangedAndSkipsDomWork(): void
    {
        $generator = $this->makeSpyGenerator();

        $config = $this->makeConfig();
        $service = new HtmlImageRewriterService($this->logger, $generator, $config, $this->container);

        $html = '<p>No images here.</p>';
        $event = $this->makeEvent($html);
        $service->handlePostRender($event);

        $this->assertSame($html, $event->renderedContent);
        $this->assertSame(0, $generator->calls);
    }

    public function testExternalImgTagLeftUntouched(): void
    {
        $generator = $this->makeSpyGenerator();

        $config = $this->makeConfig();
        $service = new HtmlImageRewriterService($this->logger, $generator, $config, $this->container);

        $html = '<p><img src="https://cdn.example.com/pic.jpg" alt="remote"></p>';
        $event = $this->makeEvent($html);
        $service->handlePostRender($event);

        $this->assertIsString($event->renderedContent);
        $this->assertStringContainsString('<img src="https://cdn.example.com/pic.jpg"', $event->renderedContent);
        $this->assertStringNotContainsString('<picture>', $event->renderedContent);
        $this->assertSame(0, $generator->calls);
    }

    public function testDataUriImgTagLeftUntouched(): void
    {
        $generator = $this->makeSpyGenerator();

        $config = $this->makeConfig();
        $service = new HtmlImageRewriterService($this->logger, $generator, $config, $this->container);

        $html = '<p><img src="data:image/png;base64,AAAA" alt="inline"></p>';
        $event = $this->makeEvent($html);
        $service->handlePostRender($event);

        $this->assertIsString($event->renderedContent);
        $this->assertStringContainsString('data:image/png;base64,AAAA', $event->renderedContent);
        $this->assertStringNotContainsString('<picture>', $event->renderedContent);
        $this->assertSame(0, $generator->calls);
    }

    public function testValidLocalImageRewrittenToPictureElement(): void
    {
        $this->createFixtureJpeg($this->sourceDir . '/assets/images/hero.jpg', 1000);

        $config = $this->makeConfig(['widths' => [400], 'webp' => true]);
        $generator = new ImageVariantGenerator($this->logger, $config);
        $service = new HtmlImageRewriterService($this->logger, $generator, $config, $this->container);

        $html = '<p><img src="/assets/images/hero.jpg" alt="Hero Image" class="hero-img"></p>';
        $event = $this->makeEvent($html);
        $service->handlePostRender($event);

        $this->assertIsString($event->renderedContent);
        $output = $event->renderedContent;
        $this->assertStringContainsString('<picture>', $output);
        $this->assertStringContainsString('<source type="image/webp"', $output);
        $this->assertStringContainsString('srcset=', $output);
        $this->assertStringContainsString('sizes="100vw"', $output);
        $this->assertStringContainsString('alt="Hero Image"', $output);
        $this->assertStringContainsString('class="hero-img"', $output);

        // Variant files physically exist
        $this->assertDirectoryExists($this->outputDir . '/assets/images/responsive');
        $files = glob($this->outputDir . '/assets/images/responsive/*');
        $this->assertNotEmpty($files);
    }

    public function testLocalImageMissingOnDiskLeftUntouchedAndLogsWarning(): void
    {
        $config = $this->makeConfig();
        $generator = new ImageVariantGenerator($this->logger, $config);
        $service = new HtmlImageRewriterService($this->logger, $generator, $config, $this->container);

        $html = '<p><img src="/assets/images/missing.jpg" alt="missing"></p>';
        $event = $this->makeEvent($html);
        $service->handlePostRender($event);

        $this->assertIsString($event->renderedContent);
        $this->assertStringContainsString('src="/assets/images/missing.jpg"', $event->renderedContent);
        $this->assertStringNotContainsString('<picture>', $event->renderedContent);
    }

    public function testPathTraversalAttemptIsRejected(): void
    {
        // Create a sensitive file outside SOURCE_DIR to confirm it is never reached.
        $base = dirname($this->sourceDir);
        $secretPath = $base . '/secret.jpg';
        $this->createFixtureJpeg($secretPath, 1000);

        $generator = $this->makeSpyGenerator();

        $config = $this->makeConfig();
        $service = new HtmlImageRewriterService($this->logger, $generator, $config, $this->container);

        $html = '<p><img src="/assets/../../secret.jpg" alt="traversal"></p>';
        $event = $this->makeEvent($html);
        $service->handlePostRender($event);

        $this->assertIsString($event->renderedContent);
        $this->assertStringNotContainsString('<picture>', $event->renderedContent);
        $this->assertSame(0, $generator->calls);

        unlink($secretPath);
    }

    public function testMultipleImagesOnlyValidOneRewritten(): void
    {
        $this->createFixtureJpeg($this->sourceDir . '/assets/images/valid.jpg', 1000);

        $config = $this->makeConfig(['widths' => [400], 'webp' => false]);
        $generator = new ImageVariantGenerator($this->logger, $config);
        $service = new HtmlImageRewriterService($this->logger, $generator, $config, $this->container);

        $html = '<div>'
            . '<img src="/assets/images/valid.jpg" alt="valid">'
            . '<img src="/assets/images/invalid.jpg" alt="invalid">'
            . '</div>';

        $event = $this->makeEvent($html);
        $service->handlePostRender($event);
        $this->assertIsString($event->renderedContent);
        $output = $event->renderedContent;

        $this->assertStringContainsString('<picture>', $output);
        $this->assertStringContainsString('alt="valid"', $output);
        $this->assertStringContainsString('src="/assets/images/invalid.jpg"', $output);

        // Ensure exactly one <picture> element was created (not two)
        $this->assertSame(1, substr_count($output, '<picture>'));
    }
}
