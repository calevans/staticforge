<?php

namespace EICC\StaticForge\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

class InitCommand extends Command
{
    protected static $defaultName = 'init';
    protected static $defaultDescription = 'Initialize a new StaticForge project';

    protected function configure(): void
    {
        $this
        ->setDescription('Initialize a new StaticForge project structure')
        ->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Overwrite existing files'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = $input->getOption('force');

        $io->title('StaticForge Project Initialization');

      // Create directory structure
        $directories = [
        'content',
        'templates',
        'public',
        'config',
        'logs'
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
                $io->success("Created directory: {$dir}/");
            } else {
                $io->note("Directory already exists: {$dir}/");
            }
        }

      // Copy bundled templates
        $this->copyBundledTemplates($io, $force);

      // Create .env file
        $this->createEnvFile($io, $force);

      // Create sample content
        $this->createSampleContent($io, $force);

        $io->success('StaticForge project initialized successfully!');
        $io->section('Next Steps:');
        $io->listing([
        'Edit .env to configure your site settings',
        'Add your content to the content/ directory',
        'Customize templates in the templates/ directory',
        'Run: staticforge render:site to build your site'
        ]);

        return Command::SUCCESS;
    }

    private function copyBundledTemplates(SymfonyStyle $io, bool $force): void
    {
      // Find the StaticForge package path where templates are bundled
        $packagePath = $this->findVendorPath();
        $bundledTemplatesPath = $packagePath . '/templates';

        if (!is_dir($bundledTemplatesPath)) {
            $io->warning("Bundled templates not found at: {$bundledTemplatesPath}");
            return;
        }

        $this->recursiveCopy($bundledTemplatesPath, 'templates', $io, $force);
    }

    private function createEnvFile(SymfonyStyle $io, bool $force): void
    {
        $envPath = '.env';

        if (file_exists($envPath) && !$force) {
            $io->note('.env file already exists. Use --force to overwrite.');
            return;
        }

        $envContent = <<<ENV
# StaticForge Configuration
SITE_NAME="My StaticForge Site"
SITE_BASE_URL="/"
SITE_DESCRIPTION="A static site built with StaticForge"

# Paths
SOURCE_DIR="content"
TEMPLATE_DIR="templates"
OUTPUT_DIR="public"
LOG_DIR="logs"

# Build Settings
DEFAULT_TEMPLATE="staticforce"
ENABLE_FEATURES="MarkdownRenderer,HtmlRenderer,MenuBuilder,Categories,Tags,ChapterNav"

# Development
DEBUG=false
LOG_LEVEL="info"
ENV;

        file_put_contents($envPath, $envContent);
        $io->success('Created .env configuration file');
    }

    private function createSampleContent(SymfonyStyle $io, bool $force): void
    {
        $indexPath = 'content/index.md';

        if (file_exists($indexPath) && !$force) {
            $io->note('Sample content already exists. Use --force to overwrite.');
            return;
        }

        $indexContent = <<<MARKDOWN
---
title: Welcome to StaticForge
description: Your new static site is ready!
template: staticforce
---

# Welcome to StaticForge

Congratulations! Your StaticForge site has been initialized successfully.

## Getting Started

1. **Edit this content** - Modify files in the `content/` directory
2. **Customize templates** - Update templates in the `templates/` directory
3. **Configure your site** - Edit the `.env` file with your site settings
4. **Build your site** - Run `staticforge render:site`

## Features

StaticForge comes with powerful features out of the box:

- **Markdown rendering** with CommonMark
- **Extensible template system** using Twig
- **Event-driven architecture** for custom features
- **Built-in navigation** and menu generation
- **Category and tag support**

## Next Steps

- Check out the [documentation](https://github.com/calevans/staticforge)
- Add more content files to the `content/` directory
- Customize your templates
- Build and deploy your site

Happy building! 🚀
MARKDOWN;

        file_put_contents($indexPath, $indexContent);
        $io->success('Created sample content file: content/index.md');
    }

    private function recursiveCopy(string $src, string $dst, SymfonyStyle $io, bool $force): void
    {
        if (!is_dir($src)) {
            return;
        }

        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }

        $files = scandir($src);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;

            if (is_dir($srcPath)) {
                $this->recursiveCopy($srcPath, $dstPath, $io, $force);
            } else {
                if (!file_exists($dstPath) || $force) {
                    copy($srcPath, $dstPath);
                    $io->text("Copied: {$dstPath}");
                } else {
                    $io->note("File exists (skipped): {$dstPath}");
                }
            }
        }
    }

    private function findVendorPath(): string
    {
      // Try to find the StaticForge package directory
        $paths = [
        __DIR__ . '/../../',              // When running from development
        getcwd() . '/vendor/eicc/staticforge/', // When installed as dependency
        __DIR__ . '/../../../../../eicc/staticforge/', // Alternative vendor structure
        ];

        foreach ($paths as $path) {
            if (is_dir($path . 'templates')) {
                return realpath($path);
            }
        }

      // Fallback to development path
        return realpath(__DIR__ . '/../../');
    }
}
