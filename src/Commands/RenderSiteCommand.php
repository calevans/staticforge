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
             );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $output->writeln('<info>StaticForge Site Generator</info>');
            $output->writeln('');

            // Get template override if provided
            $templateOverride = $input->getOption('template');

            if ($templateOverride) {
                $output->writeln("<comment>Using template override: {$templateOverride}</comment>");
            }

            // Initialize application with template override
            $application = new Application('.env', $templateOverride);

            if ($output->isVerbose()) {
                $output->writeln('<comment>Verbose mode enabled</comment>');
            }

            if ($input->getOption('clean')) {
                $output->writeln('<comment>Cleaning output directory...</comment>');
                $this->cleanOutputDirectory($application->getContainer());
            }

            $output->writeln('<info>Starting site generation...</info>');

            // Run the site generation
            $success = $application->generate();

            if (!$success) {
                throw new Exception('Site generation failed - check logs for details');
            }

            $output->writeln('');
            $output->writeln('<info>✅ Site generation completed successfully!</info>');

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