#!/usr/bin/env php
<?php
/**
 * Install default templates if they don't exist
 *
 * This script runs after composer install/update and copies the default
 * templates from the library to the project's templates directory.
 * It will NOT overwrite existing templates.
 */

// Determine if we're in vendor (library install) or project root
$vendorDir = dirname(__DIR__, 2);
$isLibraryInstall = basename(dirname(__DIR__)) === 'staticforge' &&
                    basename($vendorDir) === 'vendor';

if ($isLibraryInstall) {
    // We're installed as a library in vendor/eicc/staticforge
    $sourceTemplatesDir = __DIR__ . '/../templates';
    $targetTemplatesDir = dirname($vendorDir) . '/templates';
} else {
    // We're in the project root (development)
    echo "Running in project root - templates already in place\n";
    exit(0);
}

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

echo "StaticForge: Checking templates...\n";

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

// Show per-template status
foreach ($templateStatus as $template => $status) {
    if ($status['copied'] > 0) {
        echo "  {$template}/: Installed {$status['copied']} file(s)\n";
    } else {
        echo "  {$template}/: Skipped (already exists)\n";
    }
}

echo "StaticForge: Template check complete\n";

if ($copied === 0 && $skipped === 0) {
    echo "StaticForge: No templates found to install\n";
}

exit(0);
