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

function copyTemplates(string $source, string $target, int &$copied, int &$skipped): void
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
            // Only copy if target doesn't exist
            if (!file_exists($targetPath)) {
                copy($item->getPathname(), $targetPath);
                $copied++;
            } else {
                $skipped++;
            }
        }
    }
}

// Copy all template directories
copyTemplates($sourceTemplatesDir, $targetTemplatesDir, $copied, $skipped);

if ($copied > 0) {
    echo "Installed {$copied} template file(s) to: {$targetTemplatesDir}\n";
}

if ($skipped > 0) {
    echo "Skipped {$skipped} existing template file(s) (not overwritten)\n";
}

if ($copied === 0 && $skipped === 0) {
    echo "No templates to install\n";
}

exit(0);
