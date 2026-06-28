<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\ResponsiveImages\Services;

use EICC\StaticForge\Features\ResponsiveImages\Services\ImageVariantGenerator;
use EICC\StaticForge\Features\ResponsiveImages\Services\ResponsiveImageConfig;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\Utils\Log;
use PHPUnit\Framework\MockObject\MockObject;

class ImageVariantGeneratorTest extends UnitTestCase
{
    private Log&MockObject $logger;
    private string $workDir;
    private string $outputBaseDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = $this->createMock(Log::class);

        $this->workDir = sys_get_temp_dir() . '/staticforge_rivg_' . uniqid();
        mkdir($this->workDir, 0755, true);
        $this->outputBaseDir = $this->workDir . '/output';
        mkdir($this->outputBaseDir, 0755, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeDirectory($this->workDir);
    }

    /**
     * Creates a solid-color JPEG fixture using GD (Imagick can read GD-written JPEGs).
     */
    private function createFixtureJpeg(string $path, int $width, int $height = 0): void
    {
        $height = $height > 0 ? $height : $width;
        $width = max(1, $width);
        $height = max(1, $height);
        $im = imagecreatetruecolor($width, $height);
        if ($im === false) {
            throw new \RuntimeException('Failed to create fixture image');
        }
        $color = imagecolorallocate($im, 100, 150, 200);
        imagefill($im, 0, 0, $color === false ? 0 : $color);
        imagejpeg($im, $path);
        imagedestroy($im);
    }

    /**
     * @param int[] $widths
     */
    private function makeConfig(
        bool $enabled = true,
        array $widths = [400, 800],
        bool $webp = true,
        int $quality = 82,
        int $minSourceWidth = 400
    ): ResponsiveImageConfig {
        return new ResponsiveImageConfig(
            enabled: $enabled,
            widths: $widths,
            webp: $webp,
            quality: $quality,
            outputDir: 'assets/images/responsive',
            minSourceWidth: $minSourceWidth,
        );
    }

    public function testGeneratesOneVariantPerWidthAndFormat(): void
    {
        $sourcePath = $this->workDir . '/source.jpg';
        $this->createFixtureJpeg($sourcePath, 2000);

        $config = $this->makeConfig(widths: [400, 800], webp: true);
        $generator = new ImageVariantGenerator($this->logger, $config);

        $variants = $generator->generateVariants($sourcePath, $this->outputBaseDir, '/assets/images/responsive');

        // 2 widths x 2 formats (original + webp) = 4 variants
        $this->assertCount(4, $variants);

        $widthsSeen = array_unique(array_column($variants, 'width'));
        sort($widthsSeen);
        $this->assertSame([400, 800], $widthsSeen);

        $formatsSeen = array_unique(array_column($variants, 'format'));
        sort($formatsSeen);
        $this->assertSame(['original', 'webp'], $formatsSeen);

        foreach ($variants as $variant) {
            $this->assertFileExists($variant['path']);
        }
    }

    public function testWebpDisabledProducesOnlyOriginalFormat(): void
    {
        $sourcePath = $this->workDir . '/source.jpg';
        $this->createFixtureJpeg($sourcePath, 2000);

        $config = $this->makeConfig(widths: [400, 800], webp: false);
        $generator = new ImageVariantGenerator($this->logger, $config);

        $variants = $generator->generateVariants($sourcePath, $this->outputBaseDir, '/assets/images/responsive');

        $this->assertCount(2, $variants);
        foreach ($variants as $variant) {
            $this->assertSame('original', $variant['format']);
        }
    }

    public function testSourceNarrowerThanMinSourceWidthReturnsEmptyArray(): void
    {
        $sourcePath = $this->workDir . '/small.jpg';
        $this->createFixtureJpeg($sourcePath, 100);

        $config = $this->makeConfig(minSourceWidth: 400);
        $generator = new ImageVariantGenerator($this->logger, $config);

        $variants = $generator->generateVariants($sourcePath, $this->outputBaseDir, '/assets/images/responsive');

        $this->assertSame([], $variants);
    }

    public function testConfiguredWidthGreaterOrEqualToSourceWidthIsSkipped(): void
    {
        $sourcePath = $this->workDir . '/medium.jpg';
        $this->createFixtureJpeg($sourcePath, 500);

        // 400 < 500 (kept), 800 >= 500 (skipped, no upscale), 1200 >= 500 (skipped)
        $config = $this->makeConfig(widths: [400, 800, 1200], webp: false, minSourceWidth: 400);
        $generator = new ImageVariantGenerator($this->logger, $config);

        $variants = $generator->generateVariants($sourcePath, $this->outputBaseDir, '/assets/images/responsive');

        $this->assertCount(1, $variants);
        $this->assertSame(400, $variants[0]['width']);
    }

    public function testCorruptImageFileReturnsEmptyArrayAndLogsWarning(): void
    {
        $sourcePath = $this->workDir . '/corrupt.jpg';
        file_put_contents($sourcePath, 'this is not a real image');

        $this->logger->expects($this->atLeastOnce())
            ->method('log')
            ->with('WARNING', $this->stringContains('failed to read image dimensions'));

        $config = $this->makeConfig();
        $generator = new ImageVariantGenerator($this->logger, $config);

        $variants = $generator->generateVariants($sourcePath, $this->outputBaseDir, '/assets/images/responsive');

        $this->assertSame([], $variants);
    }

    public function testMissingSourceFileReturnsEmptyArrayAndLogsWarning(): void
    {
        $sourcePath = $this->workDir . '/does-not-exist.jpg';

        $this->logger->expects($this->once())
            ->method('log')
            ->with('WARNING', $this->stringContains('source unreadable'));

        $config = $this->makeConfig();
        $generator = new ImageVariantGenerator($this->logger, $config);

        $variants = $generator->generateVariants($sourcePath, $this->outputBaseDir, '/assets/images/responsive');

        $this->assertSame([], $variants);
    }

    public function testUnchangedSourceWithExistingVariantDoesNotRegenerate(): void
    {
        $sourcePath = $this->workDir . '/cache-source.jpg';
        $this->createFixtureJpeg($sourcePath, 1000);

        $config = $this->makeConfig(widths: [400], webp: false);
        $generator = new ImageVariantGenerator($this->logger, $config);

        $first = $generator->generateVariants($sourcePath, $this->outputBaseDir, '/assets/images/responsive');
        $this->assertCount(1, $first);
        $firstMtime = filemtime($first[0]['path']);

        // Re-run without touching the source; mtime of generated variant should be unchanged.
        sleep(1);
        $second = $generator->generateVariants($sourcePath, $this->outputBaseDir, '/assets/images/responsive');
        $this->assertCount(1, $second);
        $secondMtime = filemtime($second[0]['path']);

        $this->assertSame($firstMtime, $secondMtime);
    }

    public function testTouchingSourceTriggersRegeneration(): void
    {
        $sourcePath = $this->workDir . '/regen-source.jpg';
        $this->createFixtureJpeg($sourcePath, 1000);

        $config = $this->makeConfig(widths: [400], webp: false);
        $generator = new ImageVariantGenerator($this->logger, $config);

        $first = $generator->generateVariants($sourcePath, $this->outputBaseDir, '/assets/images/responsive');
        $firstMtime = filemtime($first[0]['path']);

        sleep(1);
        touch($sourcePath, time() + 10);

        $second = $generator->generateVariants($sourcePath, $this->outputBaseDir, '/assets/images/responsive');
        $secondMtime = filemtime($second[0]['path']);

        $this->assertGreaterThan($firstMtime, $secondMtime);
    }
}
