<?php

namespace EICC\StaticForge\Commands;

use EICC\StaticForge\Core\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Exception;

/**
 * Command to render the entire static site
 */
class RenderSiteCommand extends Command
{
    protected static $defaultName = 'render:site';
    protected static $defaultDescription = 'Generate the complete static site from content files';

    protected function configure(): void
    {
        $this->setDescription('Generate the complete static site from content files')
             ->addOption(
                 'clean',
                 'c',
                 InputOption::VALUE_NONE,
                 'Clean output directory before generation'
             )
             ->addOption(
                 'template',
                 't',
                 InputOption::VALUE_REQUIRED,
                 'Override the template theme (e.g., sample, terminal)'
             )
             ->addOption(
                 'input',
                 'i',
                 InputOption::VALUE_REQUIRED,
                 'Override input/content directory path'
             )
             ->addOption(
                 'output',
                 'o',
                 InputOption::VALUE_REQUIRED,
                 'Override output directory path'
             );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $output->writeln('<info>StaticForge Site Generator</info>');
            $output->writeln('');

            $startTime = microtime(true);

            // Get template override if provided
            $templateOverride = $input->getOption('template');

            if ($templateOverride) {
                $output->writeln("<comment>Using template override: {$templateOverride}</comment>");
            }

            // Get input/output directory overrides
            $inputOverride = $input->getOption('input');
            $outputOverride = $input->getOption('output');

            if ($inputOverride) {
                $output->writeln("<comment>Using input directory override: {$inputOverride}</comment>");
            }

            if ($outputOverride) {
                $output->writeln("<comment>Using output directory override: {$outputOverride}</comment>");
            }

            // Get env path from environment variable (for testing) or use default
            $envPath = getenv('STATICFORGE_ENV_PATH') ?: '.env';

            // Initialize application with template override
            $application = new Application($envPath, $templateOverride);
            $container = $application->getContainer();

            // Apply directory overrides after initialization
            if ($inputOverride) {
                if (!is_dir($inputOverride)) {
                    throw new Exception("Input directory does not exist: {$inputOverride}");
                }
                $container->updateVariable('SOURCE_DIR', $inputOverride);
            }

            if ($outputOverride) {
                $container->updateVariable('OUTPUT_DIR', $outputOverride);
            }

            if ($output->isVerbose()) {
                $output->writeln('<comment>Verbose mode enabled</comment>');
                $this->displayConfiguration($container, $output);
            }

            if ($input->getOption('clean')) {
                $output->writeln('<comment>Cleaning output directory...</comment>');
                $this->cleanOutputDirectory($container);
                if ($output->isVerbose()) {
                    $outputDir = $container->getVariable('OUTPUT_DIR') ?? 'public';
                    $output->writeln("  ✓ Cleaned: {$outputDir}");
                }
            }

            $output->writeln('<info>Starting site generation...</info>');

            if ($output->isVerbose()) {
                $output->writeln('');
                $output->writeln('<comment>Event Pipeline:</comment>');
                $output->writeln('  1. CREATE - Initialize features');
                $output->writeln('  2. PRE_GLOB - Prepare for file discovery');
                $output->writeln('  3. POST_GLOB - Process discovered files');
                $output->writeln('  4. PRE_LOOP - Before render loop');
                $output->writeln('  5. RENDER LOOP - Process each file');
                $output->writeln('  6. POST_LOOP - After render loop');
                $output->writeln('  7. DESTROY - Cleanup');
                $output->writeln('');
            }

            // Run the site generation
            $success = $application->generate();

            if (!$success) {
                throw new Exception('Site generation failed - check logs for details');
            }

            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);

            $output->writeln('');
            $output->writeln('<info>✅ Site generation completed successfully!</info>');

            if ($output->isVerbose()) {
                $this->displayStats($container, $output, $duration);
            } else {
                $output->writeln("<comment>Time: {$duration}s</comment>");
            }

            return Command::SUCCESS;
        } catch (Exception $e) {
            $output->writeln('');
            $output->writeln('<error>❌ Site generation failed:</error>');
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            if ($output->isVerbose()) {
                $output->writeln('');
                $output->writeln('<comment>Stack trace:</comment>');
                $output->writeln($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    /**
     * Display configuration in verbose mode
     */
    private function displayConfiguration($container, OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<comment>Configuration:</comment>');
        $output->writeln('  Content Dir: ' . ($container->getVariable('CONTENT_DIR') ?? 'content'));
        $output->writeln('  Output Dir: ' . ($container->getVariable('OUTPUT_DIR') ?? 'public'));
        $output->writeln('  Template: ' . ($container->getVariable('TEMPLATE') ?? 'default'));
        $output->writeln('  Template Dir: ' . ($container->getVariable('TEMPLATE_DIR') ?? 'templates'));
        $output->writeln('');
    }

    /**
     * Display generation statistics in verbose mode
     */
    private function displayStats($container, OutputInterface $output, float $duration): void
    {
        $output->writeln('');
        $output->writeln('<comment>Generation Statistics:</comment>');

        // Get file count from container if available
        $discoveredFiles = $container->getVariable('discovered_files') ?? [];
        $output->writeln('  Files Processed: ' . count($discoveredFiles));

        // Get feature information
        $features = $container->getVariable('features') ?? [];
        $output->writeln('  Active Features: ' . count($features));

        if (!empty($features)) {
            $output->writeln('');
            $output->writeln('  <comment>Loaded Features:</comment>');
            foreach (array_keys($features) as $featureName) {
                $output->writeln("    - {$featureName}");
            }
        }

        $output->writeln('');
        $output->writeln("  <info>Total Time: {$duration}s</info>");

        if (count($discoveredFiles) > 0) {
            $timePerFile = round($duration / count($discoveredFiles), 3);
            $output->writeln("  <comment>Average: {$timePerFile}s per file</comment>");
        }
    }

    /**
     * Clean the output directory before generation
     */
    private function cleanOutputDirectory($container): void
    {
        $outputDir = $container->getVariable('OUTPUT_DIR') ?? 'public';

        if (is_dir($outputDir)) {
            $this->removeDirectory($outputDir);
        }

        // Recreate the directory
        mkdir($outputDir, 0755, true);
    }

    /**
     * Recursively remove a directory and its contents
     */
    private function removeDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($dir);
    }
}
