<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Services;

use EICC\StaticForge\Services\TemplateRenderer;
use EICC\StaticForge\Services\TemplateVariableBuilder;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class NoindexTemplateTest extends UnitTestCase
{
    private const META = '<meta name="robots" content="noindex, follow">';

    private function renderBase(string $theme, mixed $noindex, bool $setNoindex = true): string
    {
        $this->setContainerVariable('TEMPLATE_DIR', dirname(__DIR__, 3) . '/templates');
        $this->setContainerVariable('TEMPLATE', $theme);
        $this->setContainerVariable('SITE_NAME', 'Test Site');
        $this->setContainerVariable('SITE_BASE_URL', 'https://test.example.com');
        $this->setContainerVariable('site_config', ['site' => ['name' => 'Test Site']]);

        $metadata = ['template' => 'base'];
        if ($setNoindex) {
            $metadata['noindex'] = $noindex;
        }

        $renderer = new TemplateRenderer(new TemplateVariableBuilder(), $this->container->get('logger'), null);

        return $renderer->render(
            ['metadata' => $metadata, 'content' => '<p>Body</p>', 'title' => 'Page'],
            $this->container
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function themeProvider(): array
    {
        return ['staticforce' => ['staticforce'], 'sample' => ['sample']];
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function noindexOnProvider(): array
    {
        $cases = [];
        foreach (['staticforce', 'sample'] as $theme) {
            foreach (['true' => true, 'string true' => 'true', 'string yes' => 'yes', 'int 1' => 1] as $label => $v) {
                $cases["$theme $label"] = [$theme, $v];
            }
        }

        return $cases;
    }

    #[DataProvider('noindexOnProvider')]
    public function testNoindexMetaEmittedInsideHeadForTruthyValues(string $theme, mixed $value): void
    {
        $html = $this->renderBase($theme, $value);

        $this->assertSame(1, substr_count($html, self::META));
        $metaPos = (int)strpos($html, self::META);
        $this->assertGreaterThan((int)strpos($html, '<head>'), $metaPos);
        $this->assertLessThan((int)strpos($html, '</head>'), $metaPos);
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function noindexOffProvider(): array
    {
        $cases = [];
        foreach (['staticforce', 'sample'] as $theme) {
            $values = [
                'false' => false,
                'string false' => 'false',
                'string no' => 'no',
                'string off' => 'off',
                'int 0' => 0,
                'string 0' => '0',
                'null' => null,
                'empty string' => '',
            ];
            foreach ($values as $label => $v) {
                $cases["$theme $label"] = [$theme, $v];
            }
        }

        return $cases;
    }

    #[DataProvider('noindexOffProvider')]
    public function testNoindexMetaAbsentForFalsyValues(string $theme, mixed $value): void
    {
        $this->assertStringNotContainsString(self::META, $this->renderBase($theme, $value));
    }

    #[DataProvider('themeProvider')]
    public function testNoindexMetaAbsentWhenVariableNotSet(string $theme): void
    {
        $this->assertStringNotContainsString(self::META, $this->renderBase($theme, null, false));
    }
}
