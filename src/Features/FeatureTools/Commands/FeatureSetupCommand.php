<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\FeatureTools\Commands;

use EICC\Utils\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'feature:setup',
    description: 'Setup configuration examples for a Composer-installed feature'
)]
class FeatureSetupCommand extends Command
{
    private const EXAMPLE_SUFFIX = '.example';

    protected Container $container;

    public function __construct(Container $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Setup configuration examples for a Composer-installed feature')
            ->addArgument('package', InputArgument::REQUIRED, 'The Composer package name (e.g. vendor/package)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $packageName = $input->getArgument('package');

        // Sanitize package name to prevent directory traversal
        if (strpos($packageName, '..') !== false) {
            $io->error('Invalid package name.');
            return Command::FAILURE;
        }

        $vendorDir = getcwd() . '/vendor';
        $packageDir = $vendorDir . '/' . $packageName;

        if (!is_dir($packageDir)) {
            $io->error("Package directory not found: {$packageDir}");
            $io->note("Did you run 'composer require {$packageName}'?");
            return Command::FAILURE;
        }

        $filesFound = false;
        $featureName = basename($packageName);

        // 1. Handle Single Configuration Files
        // These keep their .example suffix: they are fragments the user has to
        // merge into an existing file, so they must not land in place.
        $singleFiles = [
            'siteconfig.yaml.example' => getcwd() . "/siteconfig.yaml.example.{$featureName}",
            '.env.example' => getcwd() . "/.env.example.{$featureName}",
        ];

        foreach ($singleFiles as $sourceFile => $targetPath) {
            $sourcePath = $packageDir . '/' . $sourceFile;
            if (file_exists($sourcePath)) {
                if ($this->copyFile($sourcePath, $targetPath, $io)) {
                    $filesFound = true;
                }
            }
        }

        // 2. Handle Twig Template Examples
        // TEMPLATE is resolved by bootstrap (siteconfig site.template > .env
        // TEMPLATE > 'staticforce') and stored in the container. Reading
        // $_ENV['TEMPLATE'] here would miss a siteconfig-only template and drop
        // partials into the wrong theme.
        $templateName = (string)($this->container->getVariable('TEMPLATE') ?? 'staticforce');
        $templateRoot = (string)($this->container->getVariable('TEMPLATE_DIR') ?? (getcwd() . '/templates'));
        $templateTargetDir = rtrim($templateRoot, '/') . '/' . $templateName;

        $twigExamples = $this->findExamples($packageDir, '.html.twig.example');
        if ($twigExamples !== []) {
            $io->section('Twig partials');
            $io->text("Active template: {$templateName}");
            $io->text("Destination:     {$templateTargetDir}");

            // A missing theme directory means the resolved template name does
            // not match a real theme. Creating it would hide the mistake and
            // leave the partials somewhere the site never renders from.
            if (!is_dir($templateTargetDir)) {
                $io->error("Template directory does not exist: {$templateTargetDir}");
                $io->note(
                    "The active template is '{$templateName}'. Check 'site: template:' in siteconfig.yaml "
                    . "(or TEMPLATE in .env) and make sure that theme directory exists."
                );
                return Command::FAILURE;
            }

            if ($this->copyExamples($twigExamples, $templateTargetDir, $io)) {
                $filesFound = true;
            }
        }

        // 3. Handle CSS Examples
        $contentDir = (string)($this->container->getVariable('SOURCE_DIR') ?? (getcwd() . '/content'));
        $cssTargetDir = rtrim($contentDir, '/') . '/assets/css';

        $cssExamples = $this->findExamples($packageDir, '.css.example');
        if ($cssExamples !== []) {
            $io->section('Stylesheets');
            $io->text("Destination:     {$cssTargetDir}");

            // assets/css is an ordinary output location and may legitimately
            // not exist yet, but its content root must.
            if (!is_dir($contentDir)) {
                $io->error("Content directory does not exist: {$contentDir}");
                $io->note("Check SOURCE_DIR in .env.");
                return Command::FAILURE;
            }

            if (!is_dir($cssTargetDir) && !mkdir($cssTargetDir, 0755, true) && !is_dir($cssTargetDir)) {
                $io->error("Failed to create directory: {$cssTargetDir}");
                return Command::FAILURE;
            }

            if ($this->copyExamples($cssExamples, $cssTargetDir, $io)) {
                $filesFound = true;
            }
        }

        if (!$filesFound) {
            $io->warning("No example configuration files found in {$packageName}.");
            return Command::SUCCESS;
        }

        $io->info("Setup complete. Please review the copied .example files and merge them into your configuration.");

        return Command::SUCCESS;
    }

    private function copyFile(string $source, string $target, SymfonyStyle $io): bool
    {
        if (copy($source, $target)) {
            $io->success("Copied: {$target}");
            return true;
        }

        $io->error("Failed to copy: " . basename($source));
        return false;
    }

    /**
     * Find every example file in the package matching $suffix.
     *
     * @return array<int, string> Absolute source paths
     */
    private function findExamples(string $sourceDir, string $suffix): array
    {
        $found = [];

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), $suffix)) {
                    $found[] = $file->getPathname();
                }
            }
        } catch (\Exception $e) {
            return $found;
        }

        sort($found);

        return $found;
    }

    /**
     * Copy drop-in files into place, dropping the trailing .example so the
     * result is usable without a manual rename. An existing file is never
     * overwritten -- the user may have edited it.
     *
     * @param array<int, string> $sources
     */
    private function copyExamples(array $sources, string $targetDir, SymfonyStyle $io): bool
    {
        $filesFound = false;

        foreach ($sources as $source) {
            $filename = basename($source);
            if (str_ends_with($filename, self::EXAMPLE_SUFFIX)) {
                $filename = substr($filename, 0, -strlen(self::EXAMPLE_SUFFIX));
            }

            $target = $targetDir . '/' . $filename;

            if (file_exists($target)) {
                $io->warning("Skipped (already exists): {$target}");
                $filesFound = true;
                continue;
            }

            if ($this->copyFile($source, $target, $io)) {
                $filesFound = true;
            }
        }

        return $filesFound;
    }
}
