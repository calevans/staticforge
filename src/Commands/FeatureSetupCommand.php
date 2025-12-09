<?php

declare(strict_types=1);

namespace EICC\StaticForge\Commands;

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
        $featureName = basename($packageName); // Use the last part of package name as suffix

        // Handle siteconfig.yaml.example
        if (file_exists($packageDir . '/siteconfig.yaml.example')) {
            $target = getcwd() . "/siteconfig.yaml.example.{$featureName}";
            if (copy($packageDir . '/siteconfig.yaml.example', $target)) {
                $io->success("Copied siteconfig example to: {$target}");
                $filesFound = true;
            } else {
                $io->error("Failed to copy siteconfig.yaml.example");
            }
        }

        // Handle .env.example
        if (file_exists($packageDir . '/.env.example')) {
            $target = getcwd() . "/.env.example.{$featureName}";
            if (copy($packageDir . '/.env.example', $target)) {
                $io->success("Copied .env example to: {$target}");
                $filesFound = true;
            } else {
                $io->error("Failed to copy .env.example");
            }
        }

        if (!$filesFound) {
            $io->warning("No example configuration files (.env.example or siteconfig.yaml.example) found in {$packageName}.");
            return Command::SUCCESS;
        }

        $io->info("Please merge the contents of these files into your main .env and siteconfig.yaml files.");

        return Command::SUCCESS;
    }
}
