<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\FeatureTools\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class FeatureSetupCommand extends Command
{
    protected static $defaultName = 'feature:setup';
    protected static $defaultDescription = 'Setup configuration examples for a Composer-installed feature';

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
        $templateDir = $_ENV['TEMPLATE_DIR'] ?? (getcwd() . '/templates');
        if (!empty($_ENV['TEMPLATE'])) {
            $templateDir .= '/' . $_ENV['TEMPLATE'];
        }

        if ($this->copyRecursive($packageDir, $templateDir, '.html.twig.example', $io)) {
            $filesFound = true;
        }

        // 3. Handle CSS Examples
        $contentDir = $_ENV['SOURCE_DIR'] ?? (getcwd() . '/content');
        $cssTargetDir = $contentDir . '/assets/css';

        if ($this->copyRecursive($packageDir, $cssTargetDir, '.css.example', $io)) {
            $filesFound = true;
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

    private function copyRecursive(string $sourceDir, string $targetDir, string $suffix, SymfonyStyle $io): bool
    {
        $filesFound = false;

        try {
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceDir));
            foreach ($iterator as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), $suffix)) {
                    $target = $targetDir . '/' . $file->getFilename();
                    if ($this->copyFile($file->getPathname(), $target, $io)) {
                        $filesFound = true;
                    }
                }
            }
        } catch (\Exception $e) {
            // Log debug info if needed, but don't crash
        }

        return $filesFound;
    }
}
