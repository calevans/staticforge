<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Integration\Features\ResponsiveImages;

use EICC\StaticForge\Core\Application;
use EICC\StaticForge\Tests\Integration\IntegrationTestCase;

/**
 * Integration test for the ResponsiveImages Feature.
 *
 * Uses real temp directories (not vfsStream) for SOURCE_DIR/OUTPUT_DIR/
 * TEMPLATE_DIR because Imagick requires real filesystem paths for its
 * constructor and writeImage() calls.
 *
 * @covers \EICC\StaticForge\Features\ResponsiveImages\Feature
 */
class IntegrationTest extends IntegrationTestCase
{
    private string $baseDir;
    private string $sourceDir;
    private string $outputDir;
    private string $templateDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseDir = sys_get_temp_dir() . '/staticforge_ri_integration_' . uniqid();
        $this->sourceDir = $this->baseDir . '/source';
        $this->outputDir = $this->baseDir . '/output';
        $this->templateDir = $this->baseDir . '/templates';

        mkdir($this->sourceDir . '/assets/images', 0777, true);
        mkdir($this->outputDir, 0777, true);
        mkdir($this->templateDir . '/sample', 0777, true);

        $baseTemplate = <<<TWIG
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ title | default('Untitled Page') }}</title>
</head>
<body>
    <main>
        {{ content | raw }}
    </main>
</body>
</html>
TWIG;
        file_put_contents($this->templateDir . '/sample/base.html.twig', $baseTemplate);

        // Fixture source image wide enough to trigger variant generation
        $im = imagecreatetruecolor(1600, 900);
        if ($im === false) {
            throw new \RuntimeException('Failed to create fixture image');
        }
        $color = imagecolorallocate($im, 10, 20, 30);
        imagefill($im, 0, 0, $color === false ? 0 : $color);
        imagejpeg($im, $this->sourceDir . '/assets/images/hero.jpg');
        imagedestroy($im);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->baseDir);
        parent::tearDown();
    }

    private function configureEnv(): void
    {
        $_ENV['SITE_NAME'] = 'Responsive Images Integration Test';
        $_ENV['SOURCE_DIR'] = $this->sourceDir;
        $_ENV['OUTPUT_DIR'] = $this->outputDir;
        $_ENV['TEMPLATE_DIR'] = $this->templateDir;
        $_ENV['TEMPLATE'] = 'sample';
    }

    private function writeContentPage(): void
    {
        $html = <<<'HTML'
<!--
---
title: "Hero Page"
---
-->
<h1>Hero Page</h1>
<img src="/assets/images/hero.jpg" alt="Hero Image">
HTML;
        file_put_contents($this->sourceDir . '/index.html', $html);
    }

    public function testEnabledModeRewritesImgToPictureAndGeneratesVariants(): void
    {
        $this->writeContentPage();
        $this->configureEnv();

        $container = $this->createContainer(__DIR__ . '/../../../.env.integration');

        $siteConfig = [
            'site' => ['name' => 'Responsive Images Integration Test'],
            'responsive_images' => [
                'enabled' => true,
                'widths' => [400, 800],
                'webp' => true,
                'min_source_width' => 400,
            ],
        ];

        if ($container->hasVariable('site_config')) {
            $container->updateVariable('site_config', $siteConfig);
        } else {
            $container->setVariable('site_config', $siteConfig);
        }

        $application = new Application($container);
        $result = $application->generate();

        $this->assertTrue($result, 'Application generation should succeed');

        $outputHtmlPath = $this->outputDir . '/index.html';
        $this->assertFileExists($outputHtmlPath);

        $outputHtml = file_get_contents($outputHtmlPath);
        $this->assertNotFalse($outputHtml);

        $this->assertStringContainsString('<picture>', $outputHtml);
        $this->assertStringContainsString('<source type="image/webp"', $outputHtml);
        $this->assertStringContainsString('alt="Hero Image"', $outputHtml);

        $variantDir = $this->outputDir . '/assets/images/responsive';
        $this->assertDirectoryExists($variantDir);
        $variantFiles = glob($variantDir . '/*');
        $this->assertNotEmpty($variantFiles, 'Expected variant files to be generated under OUTPUT_DIR');
    }

    public function testDisabledByDefaultIsNoOpEndToEnd(): void
    {
        $this->writeContentPage();
        $this->configureEnv();

        $container = $this->createContainer(__DIR__ . '/../../../.env.integration');

        $siteConfig = [
            'site' => ['name' => 'Responsive Images Integration Test'],
            // responsive_images omitted entirely -> defaults to disabled
        ];

        if ($container->hasVariable('site_config')) {
            $container->updateVariable('site_config', $siteConfig);
        } else {
            $container->setVariable('site_config', $siteConfig);
        }

        $application = new Application($container);
        $result = $application->generate();

        $this->assertTrue($result, 'Application generation should succeed');

        $outputHtmlPath = $this->outputDir . '/index.html';
        $this->assertFileExists($outputHtmlPath);

        $outputHtml = file_get_contents($outputHtmlPath);
        $this->assertNotFalse($outputHtml);

        $this->assertStringNotContainsString('<picture>', $outputHtml);
        $this->assertStringContainsString('<img src="/assets/images/hero.jpg"', $outputHtml);

        $variantDir = $this->outputDir . '/assets/images/responsive';
        $this->assertDirectoryDoesNotExist($variantDir);
    }
}
