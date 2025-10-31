<?php

namespace EICC\StaticForge\Commands;

use EICC\StaticForge\Core\Application;
use EICC\Utils\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Exception;

/**
 * Command to render a single file or files matching a pattern
 */
class RenderPageCommand extends Command
{
    protected static $defaultName = 'render:page';
    protected static $defaultDescription = 'Render a single file or files matching a pattern';

    protected Container $container;

    public function __construct(Container $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function configure(): void
    {
        $this->setDescription('Render a single file or files matching a pattern')
         ->addArgument(
             'pattern',
             InputArgument::REQUIRED,
             'File path or glob pattern to render (e.g., content/about.md or content/*.md)'
         )
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
            $output->writeln('<info>StaticForge Page Renderer</info>');
            $output->writeln('');

            $pattern = $input->getArgument('pattern');
            $templateOverride = $input->getOption('template');

            if ($output->isVerbose()) {
                $output->writeln('<comment>Verbose mode enabled</comment>');
            }

            if ($templateOverride) {
                $output->writeln("<comment>Using template override: {$templateOverride}</comment>");
            }

            // Initialize application with configured container and template override
            $application = new Application($this->container, $templateOverride);

            if ($input->getOption('clean')) {
                $output->writeln('<comment>Cleaning output directory...</comment>');
                $this->cleanOutputDirectory($this->container);
            }

          // Resolve the pattern to actual files
            $files = $this->resolvePattern($pattern, $this->container, $output);

            if (empty($files)) {
                $output->writeln('<error>No files matched the pattern: ' . $pattern . '</error>');
                return Command::FAILURE;
            }

            $output->writeln("<info>Found " . count($files) . " file(s) matching pattern</info>");

            if ($output->isVerbose()) {
                foreach ($files as $file) {
                    $output->writeln("  - {$file}");
                }
            }

            $output->writeln('');
            $output->writeln('<info>Starting page generation...</info>');

          // Ensure features are loaded before rendering
            $application->ensureFeaturesLoaded();

          // Process each file through the render pipeline
            $processed = 0;
            foreach ($files as $filePath) {
                if ($this->renderFile($filePath, $application, $output)) {
                    $processed++;
                }
            }

            $output->writeln('');
            $output->writeln('<info>✅ Page generation completed successfully!</info>');
            $output->writeln("<comment>Processed {$processed} file(s)</comment>");

            return Command::SUCCESS;
        } catch (Exception $e) {
            $output->writeln('');
            $output->writeln('<error>❌ Page generation failed:</error>');
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
   * Render a single file using the Application's renderSingleFile method
   */
    private function renderFile(string $filePath, Application $application, OutputInterface $output): bool
    {
        try {
            if ($output->isVerbose()) {
                $output->writeln("  Processing: {$filePath}");
            }

          // Use Application's renderSingleFile method
            $renderContext = $application->renderSingleFile($filePath);

          // Check if file was skipped
            if ($renderContext['skip_file'] ?? false) {
                if ($output->isVerbose()) {
                    $output->writeln("    Skipped");
                }
                return false;
            }

          // Check if output was generated
            if (isset($renderContext['rendered_content']) && isset($renderContext['output_path'])) {
                if ($output->isVerbose()) {
                    $output->writeln("    → {$renderContext['output_path']}");
                }
                return true;
            }

            if ($output->isVerbose()) {
                $output->writeln("    Warning: No output generated");
            }

            return false;
        } catch (Exception $e) {
            $output->writeln("<error>  Failed to render {$filePath}: {$e->getMessage()}</error>");
            return false;
        }
    }

  /**
   * Resolve a file pattern to actual files
   *
   * @param string $pattern File pattern or path
   * @param Container $container Dependency injection container
   * @param OutputInterface $output Console output
   * @return array<string> Array of resolved file paths
   */
    private function resolvePattern(string $pattern, Container $container, OutputInterface $output): array
    {
        $contentDir = $container->getVariable('CONTENT_DIR') ?? 'content';
        $files = [];

      // If pattern doesn't start with content dir, prepend it
        if (!str_starts_with($pattern, $contentDir . '/') && !str_starts_with($pattern, $contentDir)) {
          // Check if pattern is just a filename or relative path
            if (!str_contains($pattern, '/')) {
                // Just a filename, search in content dir
                $pattern = $contentDir . '/' . $pattern;
            } elseif (!str_starts_with($pattern, '/')) {
              // Relative path, prepend content dir
                $pattern = $contentDir . '/' . $pattern;
            }
        }

        if ($output->isVerbose()) {
            $output->writeln("<comment>Resolving pattern: {$pattern}</comment>");
        }

      // Handle glob patterns
        if (str_contains($pattern, '*') || str_contains($pattern, '?')) {
            $matches = glob($pattern);
            if ($matches) {
                foreach ($matches as $match) {
                    if (is_file($match)) {
                        $files[] = $match;
                    }
                }
            }
        } else {
          // Single file
            if (file_exists($pattern) && is_file($pattern)) {
                $files[] = $pattern;
            } elseif (file_exists($pattern . '.md')) {
              // Try adding .md extension
                $files[] = $pattern . '.md';
            } elseif (file_exists($pattern . '.html')) {
              // Try adding .html extension
                $files[] = $pattern . '.html';
            }
        }

        return $files;
    }

  /**
   * Clean the output directory before generation
   *
   * @param Container $container Dependency injection container
   */
    private function cleanOutputDirectory(Container $container): void
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
