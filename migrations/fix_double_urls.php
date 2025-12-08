<?php

/**
 * StaticForge Double URL Prefix Fixer
 *
 * DESCRIPTION:
 * This script scans templates for the pattern `{{ site_base_url }}{{ variable.url }}`.
 * Since StaticForge now generates absolute URLs for file-based content (menus, category files),
 * prepending `{{ site_base_url }}` in the template results in double URLs like:
 * http://localhost:8000/http://localhost:8000/page.html
 *
 * WHAT THIS SCRIPT DOES:
 * 1. Scans ALL templates in the configured 'templates/' directory.
 * 2. Looks for `{{ site_base_url }}` followed immediately by another Twig variable that looks like a URL.
 * 3. Removes the `{{ site_base_url }}` prefix.
 *
 * PATTERNS FIXED:
 * - `{{ site_base_url }}{{ item.url }}` -> `{{ item.url }}`
 * - `{{ site_base_url }}/{{ item.url }}` -> `{{ item.url }}`
 * - `href="{{ site_base_url }}{{ item.url }}"` -> `href="{{ item.url }}"`
 *
 * USAGE:
 * lando php migrations/fix_double_urls.php
 */

require_once __DIR__ . '/../src/bootstrap.php';

use EICC\Utils\Container;

// Get container to access configuration
/** @var Container $container */
$container = require __DIR__ . '/../src/bootstrap.php';

echo "Starting Double URL Prefix Fixer...\n";
echo "===================================\n";

// 1. Determine Template Directory
$templateDir = $container->getVariable('TEMPLATE_DIR');

if (!$templateDir || !is_dir($templateDir)) {
    die("Error: Template directory not found or not configured.\n");
}

echo "Scanning templates directory: $templateDir\n\n";

// Get all subdirectories in the templates folder
$templates = array_filter(glob($templateDir . '/*'), 'is_dir');

if (empty($templates)) {
    die("No templates found in $templateDir\n");
}

$totalFilesModified = 0;

foreach ($templates as $templatePath) {
    $templateName = basename($templatePath);
    echo "Processing Template: [$templateName]\n";
    echo "-----------------------------------\n";

    // 2. Scan for Twig files in this template
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($templatePath, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'twig') {
            processTemplateFile($file->getPathname(), $templateName);
        }
    }
    echo "\n";
}

echo "===================================\n";
echo "Fix complete. Total files modified: $totalFilesModified\n";

function processTemplateFile($filePath, $templateName) {
    global $totalFilesModified;

    $content = file_get_contents($filePath);
    $originalContent = $content;
    $filename = basename($filePath);
    $modified = false;
    $changes = [];

    // Pattern 1: {{ site_base_url }} followed by {{ variable.url }} (with optional slash)
    // Matches: {{ site_base_url }}{{ item.url }}
    // Matches: {{ site_base_url }}/{{ item.url }}
    $pattern = '/\{\{\s*site_base_url\s*\}\}\/?\{\{\s*([a-zA-Z0-9_]+\.url)\s*\}\}/';

    $content = preg_replace_callback($pattern, function($matches) use (&$changes) {
        $variable = $matches[1];
        $changes[] = "Removed site_base_url prefix from {{ $variable }}";
        return "{{ $variable }}";
    }, $content, -1, $count);

    if ($count > 0) {
        $modified = true;
    }

    // Pattern 2: href="{{ site_base_url }}{{ variable }}"
    // This catches cases where the variable might not be named .url but is used in href
    // We be careful to only match if the second variable is likely a dynamic URL
    $hrefPattern = '/href=["\']\{\{\s*site_base_url\s*\}\}\/?\{\{\s*([a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)?)\s*\}\}["\']/';

    // We don't auto-fix this one blindly because it might be {{ site_base_url }}{{ image_path }} which is valid if image_path is relative.
    // But if it's .url, it's likely absolute now.

    // Let's stick to the safe pattern of .url for now.

    if ($modified && $content !== $originalContent) {
        file_put_contents($filePath, $content);
        $totalFilesModified++;
        echo "  MODIFIED: $filename\n";
        foreach ($changes as $change) {
            echo "    - $change\n";
        }
    }
}
