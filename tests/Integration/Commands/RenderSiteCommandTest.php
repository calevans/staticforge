<?php

namespace EICC\StaticForge\Tests\Integration\Commands;

use EICC\StaticForge\Tests\Integration\IntegrationTestCase;
use EICC\Utils\Container;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use EICC\StaticForge\Features\SiteBuilder\Commands\RenderSiteCommand;

/**
 * Integration tests for RenderSiteCommand
 */
class RenderSiteCommandTest extends IntegrationTestCase
{
    private string $testOutputDir;
    private string $testContentDir;
    private string $testTemplateDir;
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

      // Create temporary directories for testing
        $this->testOutputDir = sys_get_temp_dir() . '/staticforge_test_output_' . uniqid();
        $this->testContentDir = sys_get_temp_dir() . '/staticforge_test_content_' . uniqid();
        $this->testTemplateDir = sys_get_temp_dir() . '/staticforge_test_templates_' . uniqid();

        mkdir($this->testOutputDir, 0755, true);
        mkdir($this->testContentDir, 0755, true);
        mkdir($this->testTemplateDir . '/sample', 0755, true);

      // Override environment variables BEFORE loading bootstrap
        $_ENV['SOURCE_DIR'] = $this->testContentDir;
        $_ENV['OUTPUT_DIR'] = $this->testOutputDir;
        $_ENV['TEMPLATE_DIR'] = $this->testTemplateDir;

      // Load testing environment
        $this->container = $this->createContainer(__DIR__ . '/../../.env.testing');

        // Force container to use the test directories (overriding .env.testing)
        $this->container->updateVariable('SOURCE_DIR', $this->testContentDir);
        $this->container->updateVariable('OUTPUT_DIR', $this->testOutputDir);
        $this->container->updateVariable('TEMPLATE_DIR', $this->testTemplateDir);

      // Create test template
        $baseTemplate = '<!DOCTYPE html>
<html>
<head><title>{{ title | default("Test Site") }}</title></head>
<body>
    <h1>{{ title | default("Test Site") }}</h1>
    <main>{{ content | raw }}</main>
</body>
</html>';
        file_put_contents($this->testTemplateDir . '/sample/base.html.twig', $baseTemplate);

      // Create test content
        $testContent = '<!--
---
title: "Test Page"
---
-->
<h2>Test Content</h2>
<p>This is a test page.</p>';
        file_put_contents($this->testContentDir . '/test.html', $testContent);
    }

    protected function tearDown(): void
    {
      // Clean up test directories
        $this->removeDirectory($this->testOutputDir);
        $this->removeDirectory($this->testContentDir);
        $this->removeDirectory($this->testTemplateDir);

        parent::tearDown();
    }

    /**
     * Test basic site generation command
     */
    public function testRenderSiteCommandSuccess(): void
    {
        $application = new Application();
        $container = $this->container;
        $application->add(new RenderSiteCommand($container));

        $command = $application->find('site:render');
        $commandTester = new CommandTester($command);

        // Mock environment file path in command
        $result = $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $this->assertEquals(0, $result);
        $this->assertStringContainsString('Site generation completed successfully', $commandTester->getDisplay());
    }

    /**
     * Test command with verbose option
     */
    public function testRenderSiteCommandWithVerbose(): void
    {
        $application = new Application();
        $container = $this->container;
        $application->add(new RenderSiteCommand($container));

        $command = $application->find('site:render');
        $commandTester = new CommandTester($command);

        $result = $commandTester->execute([
            'command' => $command->getName(),
            '-v' => true,
        ]);

        $this->assertEquals(0, $result);
        $output = $commandTester->getDisplay();
        // Note: Verbose mode enabled is shown when using symfony's verbose option
        $this->assertStringContainsString('Site generation completed successfully', $output);
    }

    /**
     * Test command with clean option
     */
    public function testRenderSiteCommandWithClean(): void
    {
        // Create some existing files in output directory
        file_put_contents($this->testOutputDir . '/existing.html', 'old content');
        $this->assertTrue(file_exists($this->testOutputDir . '/existing.html'));

        $application = new Application();
        $container = $this->container;
        $application->add(new RenderSiteCommand($container));

        $command = $application->find('site:render');
        $commandTester = new CommandTester($command);

        $result = $commandTester->execute([
            'command' => $command->getName(),
            '--clean' => true,
        ]);

        $this->assertEquals(0, $result);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Cleaning output directory', $output);
        $this->assertStringContainsString('Site generation completed successfully', $output);
    }

    /**
     * Test command with template override
     */
    public function testRenderSiteCommandWithTemplateOverride(): void
    {
        // Create custom_template template for override test
        mkdir($this->testTemplateDir . '/custom_template', 0755, true);
        $customTemplate = '<!DOCTYPE html>
<html>
<head><title>{{ title | default("Custom Site") }}</title></head>
<body style="background: white; color: black;">
    <h1>{{ title | default("Custom Site") }}</h1>
    <main>{{ content | raw }}</main>
</body>
</html>';
        file_put_contents($this->testTemplateDir . '/custom_template/base.html.twig', $customTemplate);

        $application = new Application();
        $container = $this->container;
        $application->add(new RenderSiteCommand($container));

        $command = $application->find('site:render');
        $commandTester = new CommandTester($command);

        $result = $commandTester->execute([
            'command' => $command->getName(),
            '--template' => 'custom_template',
        ]);

        $this->assertEquals(0, $result);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Using template override: custom_template', $output);
        $this->assertStringContainsString('Site generation completed successfully', $output);
    }

    /**
     * Test command with invalid template
     */
    public function testRenderSiteCommandWithInvalidTemplate(): void
    {
        $application = new Application();
        $container = $this->container;
        $application->add(new RenderSiteCommand($container));

        $command = $application->find('site:render');
        $commandTester = new CommandTester($command);

        $result = $commandTester->execute([
            'command' => $command->getName(),
            '--template' => 'nonexistent',
        ]);

        $this->assertEquals(1, $result);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Site generation failed', $output);
        $this->assertStringContainsString('Template \'nonexistent\' not found', $output);
        $this->assertStringContainsString('Available templates:', $output);
    }

    /**
     * Test command configuration and options
     */
    public function testRenderSiteCommandConfiguration(): void
    {
        $container = $this->container;
        $command = new RenderSiteCommand($container);

        // Test command basic configuration
        $this->assertEquals('site:render', $command->getName());
        $this->assertEquals('Generate the complete static site from content files', $command->getDescription());

    // Test that options are properly configured
        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasOption('clean'));
        $this->assertTrue($definition->hasOption('template'));
        $this->assertTrue($definition->hasOption('input'));
        $this->assertTrue($definition->hasOption('output'));

        $cleanOption = $definition->getOption('clean');
        $this->assertEquals('Clean output directory before generation', $cleanOption->getDescription());

        $templateOption = $definition->getOption('template');
        $this->assertEquals('Override the site template (e.g., sample, staticforce)', $templateOption->getDescription());

        $inputOption = $definition->getOption('input');
        $this->assertEquals('Override input/content directory path', $inputOption->getDescription());

        $outputOption = $definition->getOption('output');
        $this->assertEquals('Override output directory path', $outputOption->getDescription());
    }

  /**
   * Test command with input directory override
   */
    public function testRenderSiteCommandWithInputOverride(): void
    {
      // Create alternate input directory
        $altInputDir = sys_get_temp_dir() . '/staticforge_alt_input_' . uniqid();
        mkdir($altInputDir, 0755, true);

        $altContent = '<!--
---
title: "Alt Page"
---
-->
<h2>Alt Content</h2>';
        file_put_contents($altInputDir . '/alt.html', $altContent);

        $application = new Application();
        $container = $this->container;
        $application->add(new RenderSiteCommand($container));

        $command = $application->find('site:render');
        $commandTester = new CommandTester($command);

        $result = $commandTester->execute([
        'command' => $command->getName(),
        '--input' => $altInputDir,
        ]);

        $this->assertEquals(0, $result);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Using input directory override: ' . $altInputDir, $output);
        $this->assertStringContainsString('Site generation completed successfully', $output);

      // Check that alt.html was generated
        $this->assertFileExists($this->testOutputDir . '/alt.html');

      // Cleanup
        $this->removeDirectory($altInputDir);
    }

  /**
   * Test command with output directory override
   */
    public function testRenderSiteCommandWithOutputOverride(): void
    {
      // Create alternate output directory
        $altOutputDir = sys_get_temp_dir() . '/staticforge_alt_output_' . uniqid();
        mkdir($altOutputDir, 0755, true);

        $application = new Application();
        $container = $this->container;
        $application->add(new RenderSiteCommand($container));

        $command = $application->find('site:render');
        $commandTester = new CommandTester($command);

        $result = $commandTester->execute([
        'command' => $command->getName(),
        '--output' => $altOutputDir,
        ]);

        $this->assertEquals(0, $result);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Using output directory override: ' . $altOutputDir, $output);
        $this->assertStringContainsString('Site generation completed successfully', $output);

      // Check that file was generated in alt output directory
        $this->assertFileExists($altOutputDir . '/test.html');
        $this->assertFileDoesNotExist($this->testOutputDir . '/test.html');

      // Cleanup
        $this->removeDirectory($altOutputDir);
    }

  /**
   * Test command with both input and output overrides
   */
    public function testRenderSiteCommandWithBothOverrides(): void
    {
      // Create alternate directories
        $altInputDir = sys_get_temp_dir() . '/staticforge_both_input_' . uniqid();
        $altOutputDir = sys_get_temp_dir() . '/staticforge_both_output_' . uniqid();
        mkdir($altInputDir, 0755, true);
        mkdir($altOutputDir, 0755, true);

        $altContent = '<!--
---
title: "Both Override"
---
-->
<h2>Both directories overridden</h2>';
        file_put_contents($altInputDir . '/both.html', $altContent);

        $application = new Application();
        $container = $this->container;
        $application->add(new RenderSiteCommand($container));

        $command = $application->find('site:render');
        $commandTester = new CommandTester($command);

        $result = $commandTester->execute([
        'command' => $command->getName(),
        '--input' => $altInputDir,
        '--output' => $altOutputDir,
        '--clean' => true,
        ]);

        $this->assertEquals(0, $result);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Using input directory override: ' . $altInputDir, $output);
        $this->assertStringContainsString('Using output directory override: ' . $altOutputDir, $output);
        $this->assertStringContainsString('Site generation completed successfully', $output);

      // Check that file was generated in alt output directory from alt input
        $this->assertFileExists($altOutputDir . '/both.html');
        $this->assertFileDoesNotExist($this->testOutputDir . '/both.html');
        $this->assertFileDoesNotExist($altOutputDir . '/test.html');

      // Cleanup
        $this->removeDirectory($altInputDir);
        $this->removeDirectory($altOutputDir);
    }

  /**
   * Test command with invalid input directory
   */
    public function testRenderSiteCommandWithInvalidInput(): void
    {
        $application = new Application();
        $container = $this->container;
        $application->add(new RenderSiteCommand($container));

        $command = $application->find('site:render');
        $commandTester = new CommandTester($command);

        $result = $commandTester->execute([
        'command' => $command->getName(),
        '--input' => '/nonexistent/directory',
        ]);

        $this->assertEquals(1, $result);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Site generation failed', $output);
        $this->assertStringContainsString('Input directory does not exist', $output);
    }
}
