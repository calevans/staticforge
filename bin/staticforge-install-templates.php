#!/usr/bin/env php
<?php
/**
 * Install StaticForge default templates
 *
 * Usage: vendor/bin/install-templates.php
 */

// Find the templates source directory
$possiblePaths = [
    __DIR__ . '/../templates',                           // Running from vendor/eicc/staticforge/bin
    __DIR__ . '/../../../../eicc/staticforge/templates', // Alternative vendor structure
];

$sourceTemplatesDir = null;
foreach ($possiblePaths as $path) {
    if (is_dir($path)) {
        $sourceTemplatesDir = realpath($path);
        break;
    }
}

if (!$sourceTemplatesDir) {
    echo "Error: Could not find StaticForge templates directory\n";
    exit(1);
}

// Target is always the project root's templates directory
$targetTemplatesDir = getcwd() . '/templates';

// Check if target templates directory exists
if (!is_dir($targetTemplatesDir)) {
    mkdir($targetTemplatesDir, 0755, true);
    echo "Created templates directory: {$targetTemplatesDir}\n";
}

// Copy template directories recursively
$copied = 0;
$skipped = 0;
$templateStatus = [];

function copyTemplates(string $source, string $target, int &$copied, int &$skipped, array &$templateStatus): void
{
    if (!is_dir($source)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $relativePath = substr($item->getPathname(), strlen($source) + 1);
        $targetPath = $target . DIRECTORY_SEPARATOR . $relativePath;

        if ($item->isDir()) {
            if (!is_dir($targetPath)) {
                mkdir($targetPath, 0755, true);
            }
        } else {
            // Extract template directory (first path component)
            $parts = explode(DIRECTORY_SEPARATOR, $relativePath);
            $templateDir = $parts[0];

            if (!isset($templateStatus[$templateDir])) {
                $templateStatus[$templateDir] = ['copied' => 0, 'skipped' => 0];
            }

            // Only copy if target doesn't exist
            if (!file_exists($targetPath)) {
                copy($item->getPathname(), $targetPath);
                $copied++;
                $templateStatus[$templateDir]['copied']++;
            } else {
                $skipped++;
                $templateStatus[$templateDir]['skipped']++;
            }
        }
    }
}

echo "StaticForge: Installing templates...\n";

// Copy all template directories
copyTemplates($sourceTemplatesDir, $targetTemplatesDir, $copied, $skipped, $templateStatus);

// Show per-template status
foreach ($templateStatus as $template => $status) {
    if ($status['copied'] > 0) {
        echo "  {$template}/: Installed {$status['copied']} file(s)\n";
    } else {
        echo "  {$template}/: Skipped (already exists)\n";
    }
}

echo "StaticForge: Template installation complete\n";

if ($copied > 0) {
    echo "\nInstalled {$copied} template file(s) to {$targetTemplatesDir}\n";
}
