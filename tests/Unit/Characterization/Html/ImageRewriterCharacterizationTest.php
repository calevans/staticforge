<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Characterization\Html;

use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Features\ResponsiveImages\Services\HtmlImageRewriterService;
use EICC\StaticForge\Features\ResponsiveImages\Services\ImageVariantGenerator;
use EICC\StaticForge\Features\ResponsiveImages\Services\ResponsiveImageConfig;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\Utils\Log;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Freezes the 3.3.7 output of HtmlImageRewriterService::handlePostRender for the shared HTML
 * corpus. Every corpus fragment is combined with one rewritable <img>, because the service only
 * re-serializes the DOM when at least one image was replaced; plus a few image-specific fragments.
 *
 * The variant generator is replaced with a deterministic stub (no Imagick, no image files written),
 * so only the DOM parse/rewrite/serialize step is characterized.
 */
class ImageRewriterCharacterizationTest extends UnitTestCase
{
    use GoldenAssertions;

    private const GROUP = 'images';
    private const HERO = '<p><img src="/assets/images/hero.jpg" alt="hero &amp; friends" '
        . 'class="wide" loading="lazy"></p>';

    private string $baseDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseDir = sys_get_temp_dir() . '/staticforge_char_img_' . uniqid('', true);
        mkdir($this->baseDir . '/source/assets/images', 0755, true);
        mkdir($this->baseDir . '/output', 0755, true);
        mkdir($this->baseDir . '/templates/sample', 0755, true);
        file_put_contents($this->baseDir . '/source/assets/images/hero.jpg', 'not-a-real-jpeg');

        $this->setContainerVariable('SOURCE_DIR', $this->baseDir . '/source');
        $this->setContainerVariable('OUTPUT_DIR', $this->baseDir . '/output');
        $this->setContainerVariable('TEMPLATE_DIR', $this->baseDir . '/templates');
        $this->setContainerVariable('TEMPLATE', 'sample');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->baseDir);
        parent::tearDown();
    }

    /**
     * @return array<string, string> case name => input HTML
     */
    private static function inputs(): array
    {
        $inputs = [];
        foreach (HtmlCorpus::cases() as $name => $html) {
            $inputs[$name] = $html . self::HERO;
        }

        $inputs['no_img_tag_early_return'] = '<p>No images &amp; nothing to do &nbsp;</p>';
        $inputs['external_and_data_images_untouched'] = '<p><img src="https://cdn.example.com/a.jpg">'
            . '<img src="//cdn.example.com/b.jpg"><img src="data:image/png;base64,AAAA"></p>';
        $inputs['unresolvable_local_image_untouched'] = '<p><img src="/assets/images/missing.jpg" alt="m"></p>';
        $inputs['img_without_src_untouched'] = '<p><img alt="nosrc"></p>';
        $inputs['img_inside_link_and_figure'] = '<figure><a href="/big"><img src="/assets/images/hero.jpg" alt="x"></a>'
            . '<figcaption>Caption &amp; more</figcaption></figure>';
        $inputs['two_rewritable_images_with_unicode'] = '<p>日本語 🎉 <img src="/assets/images/hero.jpg" alt="一">'
            . '<img src="/assets/images/hero.jpg" alt="二"></p>';

        return $inputs;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function inputProvider(): array
    {
        $out = [];
        foreach (array_keys(self::inputs()) as $name) {
            $out[$name] = [$name];
        }

        return $out;
    }

    private function rewrite(string $html): string
    {
        $config = new ResponsiveImageConfig(
            enabled: true,
            widths: [400, 800],
            webp: true,
            quality: 82,
            outputDir: 'assets/images/responsive',
            minSourceWidth: 400,
        );
        $logger = $this->createStub(Log::class);
        $generator = new class ($logger, $config) extends ImageVariantGenerator {
            public function generateVariants(string $sourcePath, string $outputBaseDir, string $urlBaseDir): array
            {
                return [
                    [
                        'width' => 400,
                        'path' => 'p/hero-400.jpg',
                        'url' => '/assets/images/responsive/hero-400.jpg',
                        'format' => 'original',
                    ],
                    [
                        'width' => 400,
                        'path' => 'p/hero-400.webp',
                        'url' => '/assets/images/responsive/hero-400.webp',
                        'format' => 'webp',
                    ],
                    [
                        'width' => 800,
                        'path' => 'p/hero-800.jpg',
                        'url' => '/assets/images/responsive/hero-800.jpg',
                        'format' => 'original',
                    ],
                    [
                        'width' => 800,
                        'path' => 'p/hero-800.webp',
                        'url' => '/assets/images/responsive/hero-800.webp',
                        'format' => 'webp',
                    ],
                ];
            }
        };

        $service = new HtmlImageRewriterService($logger, $generator, $config, $this->container);
        $event = new RenderEvent(name: 'POST_RENDER', filePath: '', fileUrl: '', metadata: [], renderedContent: $html);
        $service->handlePostRender($event);

        $this->assertIsString($event->renderedContent);

        return $event->renderedContent;
    }

    #[DataProvider('inputProvider')]
    public function testHandlePostRenderOutputMatchesFrozenGolden(string $case): void
    {
        $this->assertMatchesGolden(self::GROUP, $case, $this->rewrite(self::inputs()[$case]));
    }

    public function testGoldenSetMatchesInputs(): void
    {
        $this->assertNoOrphanedGoldens(self::GROUP, array_keys(self::inputs()));
    }
}
