<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\SiteBuilder\Commands;

use EICC\StaticForge\Features\SiteBuilder\Commands\RenderSiteCommand;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use ReflectionMethod;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Unit tests for RenderSiteCommand business-logic helper methods.
 *
 * Full end-to-end execution of the command (via CommandTester running a
 * real Application::generate()) is covered by
 * tests/Integration/Commands/RenderSiteCommandTest.php. These unit tests
 * exercise the private filesystem helpers directly so failure paths
 * (missing OUTPUT_DIR, non-existent directories) are covered without the
 * overhead of a full site generation run.
 *
 * @covers \EICC\StaticForge\Features\SiteBuilder\Commands\RenderSiteCommand
 */
class RenderSiteCommandTest extends UnitTestCase
{
    private RenderSiteCommand $command;
    private string $outputDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->command = new RenderSiteCommand($this->container);
        $this->outputDir = sys_get_temp_dir() . '/staticforge_rendercmd_' . uniqid();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (is_dir($this->outputDir)) {
            $this->removeDirectory($this->outputDir);
        }
    }

    public function testConfigureRegistersExpectedOptions(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('clean'));
        $this->assertTrue($definition->hasOption('template'));
        $this->assertTrue($definition->hasOption('input'));
        $this->assertTrue($definition->hasOption('output'));
        $this->assertTrue($definition->hasOption('include-drafts'));
    }

    public function testGetNameAndDescription(): void
    {
        $this->assertEquals('site:render', $this->command->getName());
        $this->assertEquals(
            'Generate the complete static site from content files',
            $this->command->getDescription()
        );
    }

    public function testCleanOutputDirectoryThrowsWhenOutputDirNotSet(): void
    {
        $container = new \EICC\Utils\Container();
        $command = new RenderSiteCommand($container);

        $method = new ReflectionMethod(RenderSiteCommand::class, 'cleanOutputDirectory');
        $method->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OUTPUT_DIR not set in container');

        $method->invoke($command, $container);
    }

    public function testCleanOutputDirectoryRemovesExistingFilesAndRecreatesDirectory(): void
    {
        mkdir($this->outputDir, 0755, true);
        file_put_contents($this->outputDir . '/stale.html', 'old content');
        mkdir($this->outputDir . '/subdir', 0755, true);
        file_put_contents($this->outputDir . '/subdir/nested.html', 'nested content');

        $container = new \EICC\Utils\Container();
        $container->setVariable('OUTPUT_DIR', $this->outputDir);
        $command = new RenderSiteCommand($container);

        $method = new ReflectionMethod(RenderSiteCommand::class, 'cleanOutputDirectory');
        $method->setAccessible(true);
        $method->invoke($command, $container);

        $this->assertDirectoryExists($this->outputDir);
        $this->assertFileDoesNotExist($this->outputDir . '/stale.html');
        $this->assertDirectoryDoesNotExist($this->outputDir . '/subdir');
    }

    public function testCleanOutputDirectoryCreatesDirectoryWhenItDoesNotExist(): void
    {
        // Do not pre-create $this->outputDir
        $container = new \EICC\Utils\Container();
        $container->setVariable('OUTPUT_DIR', $this->outputDir);
        $command = new RenderSiteCommand($container);

        $method = new ReflectionMethod(RenderSiteCommand::class, 'cleanOutputDirectory');
        $method->setAccessible(true);
        $method->invoke($command, $container);

        $this->assertDirectoryExists($this->outputDir);
    }

    public function testRemoveDirectoryReturnsFalseForNonDirectory(): void
    {
        $method = new ReflectionMethod(RenderSiteCommand::class, 'removeDirectory');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, '/path/does/not/exist');

        $this->assertFalse($result);
    }

    public function testRemoveDirectoryRecursivelyDeletesNestedContent(): void
    {
        mkdir($this->outputDir, 0755, true);
        mkdir($this->outputDir . '/a/b', 0755, true);
        file_put_contents($this->outputDir . '/a/b/file.txt', 'x');
        file_put_contents($this->outputDir . '/top.txt', 'y');

        $method = new ReflectionMethod(RenderSiteCommand::class, 'removeDirectory');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, $this->outputDir);

        $this->assertTrue($result);
        $this->assertDirectoryDoesNotExist($this->outputDir);
    }

    public function testDisplayConfigurationShowsNotSetWhenVariablesMissing(): void
    {
        $container = new \EICC\Utils\Container();
        $command = new RenderSiteCommand($container);

        $method = new ReflectionMethod(RenderSiteCommand::class, 'displayConfiguration');
        $method->setAccessible(true);

        $output = new BufferedOutput();
        $method->invoke($command, $container, $output);

        $display = $output->fetch();
        $this->assertStringContainsString('Source Dir: Not Set', $display);
        $this->assertStringContainsString('Output Dir: Not Set', $display);
        $this->assertStringContainsString('Template: default', $display);
    }

    public function testDisplayConfigurationShowsConfiguredValues(): void
    {
        $container = new \EICC\Utils\Container();
        $container->setVariable('SOURCE_DIR', '/src');
        $container->setVariable('OUTPUT_DIR', '/out');
        $container->setVariable('TEMPLATE', 'sample');
        $container->setVariable('TEMPLATE_DIR', '/tpl');
        $command = new RenderSiteCommand($container);

        $method = new ReflectionMethod(RenderSiteCommand::class, 'displayConfiguration');
        $method->setAccessible(true);

        $output = new BufferedOutput();
        $method->invoke($command, $container, $output);

        $display = $output->fetch();
        $this->assertStringContainsString('Source Dir: /src', $display);
        $this->assertStringContainsString('Output Dir: /out', $display);
        $this->assertStringContainsString('Template: sample', $display);
        $this->assertStringContainsString('Template Dir: /tpl', $display);
    }

    public function testDisplayStatsHandlesNoFilesProcessed(): void
    {
        $container = new \EICC\Utils\Container();
        $command = new RenderSiteCommand($container);

        $method = new ReflectionMethod(RenderSiteCommand::class, 'displayStats');
        $method->setAccessible(true);

        $output = new BufferedOutput();
        $method->invoke($command, $container, $output, 1.23);

        $display = $output->fetch();
        $this->assertStringContainsString('Files Processed: 0', $display);
        $this->assertStringContainsString('Active Features: 0', $display);
        $this->assertStringContainsString('Total Time: 1.23s', $display);
        // No "Average" line should be shown when there are no files
        $this->assertStringNotContainsString('Average:', $display);
    }

    public function testDisplayStatsSeparatesStandardAndCustomFeatures(): void
    {
        $container = new \EICC\Utils\Container();
        $container->setVariable('discovered_files', [['path' => 'a.md'], ['path' => 'b.md']]);
        $container->setVariable('features', [
            'MarkdownRenderer' => ['type' => 'Standard'],
            'MyCustomFeature' => ['type' => 'Custom'],
        ]);
        $command = new RenderSiteCommand($container);

        $method = new ReflectionMethod(RenderSiteCommand::class, 'displayStats');
        $method->setAccessible(true);

        $output = new BufferedOutput();
        $method->invoke($command, $container, $output, 2.0);

        $display = $output->fetch();
        $this->assertStringContainsString('Files Processed: 2', $display);
        $this->assertStringContainsString('Active Features: 2', $display);
        $this->assertStringContainsString('Standard Features:', $display);
        $this->assertStringContainsString('MarkdownRenderer', $display);
        $this->assertStringContainsString('Custom Features:', $display);
        $this->assertStringContainsString('MyCustomFeature', $display);
        $this->assertStringContainsString('Average:', $display);
    }
}
