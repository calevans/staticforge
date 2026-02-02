<?php

namespace EICC\StaticForge\Features\SiteBuilder\Commands;

use EICC\StaticForge\Core\Application;
use EICC\Utils\Container;
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
    protected static $defaultName = 'site:render';
    protected static $defaultDescription = 'Generate the complete static site from content files';

    protected Container $container;

    public function __construct(Container $container)
    {
        parent::__construct();
        $this->container = $container;
    }

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
                 'Override the site template (e.g., sample, staticforce)'
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

            // Get template override if provided
            $templateOverride = $input->getOption('template');

            // Initialize application with configured container and template override
            $application = new Application($this->container, $templateOverride);

            // Apply directory overrides after initialization
            if ($inputOverride) {
                if (!is_dir($inputOverride)) {
                    throw new Exception("Input directory does not exist: {$inputOverride}");
                }
                $this->container->updateVariable('SOURCE_DIR', $inputOverride);
            }

            if ($outputOverride) {
                $this->container->updateVariable('OUTPUT_DIR', $outputOverride);
            }

            if ($output->isVerbose()) {
                $output->writeln('<comment>Verbose mode enabled</comment>');
                $this->displayConfiguration($this->container, $output);
            }

            if ($input->getOption('clean')) {
                $output->writeln('<comment>Cleaning output directory...</comment>');
                $this->cleanOutputDirectory($this->container);
                if ($output->isVerbose()) {
                    $outputDir = $this->container->getVariable('OUTPUT_DIR');
                    if (!$outputDir) {
                        throw new \RuntimeException('OUTPUT_DIR not set in container');
                    }
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
                $output->writeln('     - PRE_RENDER');
                $output->writeln('     - RENDER');
                $output->writeln('     - POST_RENDER');
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
                $this->displayStats($this->container, $output, $duration);
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
     *
     * @param Container $container Dependency injection container
     * @param OutputInterface $output Console output
     */
    private function displayConfiguration(Container $container, OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<comment>Configuration:</comment>');
        $output->writeln('  Source Dir: ' . ($container->getVariable('SOURCE_DIR') ?? 'Not Set'));
        $output->writeln('  Output Dir: ' . ($container->getVariable('OUTPUT_DIR') ?? 'Not Set'));
        $output->writeln('  Template: ' . ($container->getVariable('TEMPLATE') ?? 'default'));
        $output->writeln('  Template Dir: ' . ($container->getVariable('TEMPLATE_DIR') ?? 'Not Set'));
        $output->writeln('');
    }

    /**
     * Display generation statistics in verbose mode
     *
     * @param Container $container Dependency injection container
     * @param OutputInterface $output Console output
     * @param float $duration Generation time in seconds
     */
    private function displayStats(Container $container, OutputInterface $output, float $duration): void
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
            $standardFeatures = [];
            $customFeatures = [];

            foreach ($features as $name => $data) {
                if (isset($data['type']) && $data['type'] === 'Custom') {
                    $customFeatures[] = $name;
                } else {
                    $standardFeatures[] = $name;
                }
            }

            if (!empty($standardFeatures)) {
                $output->writeln('');
                $output->writeln('  <comment>Standard Features:</comment>');
                sort($standardFeatures);
                foreach ($standardFeatures as $featureName) {
                    $output->writeln("    - {$featureName}");
                }
            }

            if (!empty($customFeatures)) {
                $output->writeln('');
                $output->writeln('  <comment>Custom Features:</comment>');
                sort($customFeatures);
                foreach ($customFeatures as $featureName) {
                    $output->writeln("    - {$featureName}");
                }
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
     *
     * @param Container $container Dependency injection container
     */
    private function cleanOutputDirectory(Container $container): void
    {
        $outputDir = $container->getVariable('OUTPUT_DIR');
        if (!$outputDir) {
            throw new \RuntimeException('OUTPUT_DIR not set in container');
        }

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
