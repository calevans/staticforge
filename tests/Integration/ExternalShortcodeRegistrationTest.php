<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Integration;

use EICC\StaticForge\Core\Application;
use EICC\StaticForge\Shortcodes\ShortcodeInterface;
use EICC\StaticForge\Shortcodes\ShortcodeManager;
use EICC\Utils\Container;

/**
 * Reproduces a real production regression: an external Feature package
 * (e.g. staticforge-gallery) registers its shortcode by pulling
 * ShortcodeManager straight out of the container from a CONSOLE_INIT
 * listener, entirely outside of ShortcodeProcessor's own construction.
 * That only works if ShortcodeManager is registered under its own class
 * name and is the exact same instance ShortcodeProcessorService uses to
 * process content — not a private implementation detail only reachable
 * via ShortcodeProcessorService's constructor.
 */
class ExternalShortcodeRegistrationTest extends IntegrationTestCase
{
    private string $testOutputDir;
    private string $testContentDir;
    private string $testTemplateDir;
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testOutputDir = sys_get_temp_dir() . '/staticforge_shortcode_output_' . uniqid();
        $this->testContentDir = sys_get_temp_dir() . '/staticforge_shortcode_content_' . uniqid();
        $this->testTemplateDir = sys_get_temp_dir() . '/staticforge_shortcode_templates_' . uniqid();

        mkdir($this->testOutputDir, 0755, true);
        mkdir($this->testContentDir, 0755, true);
        mkdir($this->testTemplateDir . '/sample', 0755, true);

        $_ENV['SOURCE_DIR'] = $this->testContentDir;
        $_ENV['OUTPUT_DIR'] = $this->testOutputDir;
        $_ENV['TEMPLATE_DIR'] = $this->testTemplateDir;

        $this->container = $this->createContainer(__DIR__ . '/../.env.integration');

        file_put_contents(
            $this->testTemplateDir . '/sample/base.html.twig',
            "<!DOCTYPE html>\n<html><body>{{ content | raw }}</body></html>"
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeDirectory($this->testOutputDir);
        $this->removeDirectory($this->testContentDir);
        $this->removeDirectory($this->testTemplateDir);
    }

    public function testShortcodeRegisteredExternallyViaContainerIsProcessed(): void
    {
        $this->assertTrue(
            $this->container->has(ShortcodeManager::class),
            'ShortcodeManager must be reachable via the container for external ' .
            'Feature packages (e.g. staticforge-gallery) to register a shortcode ' .
            'from outside ShortcodeProcessor itself.'
        );

        // Simulate what an external Feature's CONSOLE_INIT listener does.
        $shortcodeManager = $this->container->get(ShortcodeManager::class);
        $shortcodeManager->register(new class implements ShortcodeInterface {
            public function getName(): string
            {
                return 'externaltest';
            }

            public function handle(array $attributes, string $content = ''): string
            {
                return '<div class="external-shortcode">' . ($attributes['label'] ?? '') . '</div>';
            }
        });

        file_put_contents(
            $this->testContentDir . '/page.md',
            "---\ntitle: \"Page\"\n---\n[[externaltest label=\"hello\"]]"
        );

        $app = new Application($this->container);
        $this->expectOutputString('');
        $result = $app->generate();

        $this->assertTrue($result);

        $html = file_get_contents($this->testOutputDir . '/page.html');
        $this->assertNotFalse($html);
        $this->assertStringContainsString(
            '<div class="external-shortcode">hello</div>',
            $html,
            'The shortcode registered via $container->get(ShortcodeManager::class) ' .
            'from outside ShortcodeProcessor was not processed by the real render ' .
            'pipeline — it is not the same instance ShortcodeProcessorService uses.'
        );
    }
}
