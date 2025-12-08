<?php

/**
 * StaticForge Base Tag Migration Tool
 * 
 * DESCRIPTION:
 * This script migrates StaticForge templates from the legacy "<base> tag" architecture 
 * to the new "Absolute URL" architecture.
 * 
 * BACKGROUND:
 * Previously, StaticForge templates used a <base href="{{ site_base_url }}"> tag in the <head>.
 * This allowed relative links (e.g., <link href="assets/css/style.css">) to work from any 
 * subdirectory. However, this broke relative links in Markdown content (e.g., [Link](page.md)),
 * causing them to resolve to the site root instead of relative to the current page.
 * 
 * THE FIX:
 * 1. Remove the <base> tag from templates.
 * 2. Update all asset links (CSS, JS, Images) to use absolute paths prefixed with {{ site_base_url }}.
 * 
 * WHAT THIS SCRIPT DOES:
 * 1. Scans ALL templates in the configured 'templates/' directory.
 * 2. Removes <base> tags (both HTML and Twig-wrapped versions).
 * 3. Finds relative asset links (starting with assets/, css/, js/, images/, img/) and prepends {{ site_base_url }}.
 * 
 * USAGE:
 * lando php migrations/migrate_base_tag.php
 * 
 * WARNING:
 * This script modifies files in place. Ensure you have a backup or clean git state before running.
 */

require_once __DIR__ . '/../src/bootstrap.php';

use EICC\Utils\Container;

// Get container to access configuration
/** @var Container $container */
$container = require __DIR__ . '/../src/bootstrap.php';

echo "Starting Base Tag Migration Tool...\n";
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
echo "Migration complete. Total files modified: $totalFilesModified\n";

function processTemplateFile($filePath, $templateName) {
    global $totalFilesModified;
    
    $content = file_get_contents($filePath);
    $originalContent = $content;
    $filename = basename($filePath);
    $modified = false;
    $changes = [];

    // 3. Remove <base> tag
    // Handle Twig wrapped version: {% if site_base_url %}<base ...>{% endif %}
    $twigBasePattern = '/{%\s*if\s*site_base_url\s*%}\s*<base[^>]+>\s*{%\s*endif\s*%}/s';
    if (preg_match($twigBasePattern, $content)) {
        $content = preg_replace($twigBasePattern, '', $content);
        $changes[] = "Removed Twig-wrapped <base> tag";
        $modified = true;
    }

    // Handle standalone HTML version: <base href="...">
    $htmlBasePattern = '/<base\s+href=[^>]+>/i';
    if (preg_match($htmlBasePattern, $content)) {
        $content = preg_replace($htmlBasePattern, '', $content);
        $changes[] = "Removed HTML <base> tag";
        $modified = true;
    }

    // 4. Update Asset Links
    // Look for href="..." or src="..." that are relative paths
    // Exclude: http://, https://, //, {{ (already twig variables), # (anchors), mailto:
    
    $assetPattern = '/(href|src)=(["\'])(?!(?:https?:)?\/\/|\{\{|#|mailto:)([^"\']+)\2/i';
    
    $content = preg_replace_callback($assetPattern, function($matches) use ($filename, &$modified, &$changes) {
        $attr = $matches[1];
        $quote = $matches[2];
        $path = $matches[3];

        // Heuristic: If it starts with assets/, css/, js/, images/, img/ it's likely a site asset
        if (preg_match('/^(assets|css|js|images|img)\//i', $path)) {
            // Check if already prefixed (double check)
            if (strpos($path, '{{ site_base_url }}') === false) {
                return sprintf('%s=%s{{ site_base_url }}%s%s', $attr, $quote, $path, $quote);
            }
        }
        
        return $matches[0]; // No change
    }, $content, -1, $count);

    if ($count > 0) {
        $changes[] = "Updated $count asset links";
        $modified = true;
    }

    if ($modified && $content !== $originalContent) {
        file_put_contents($filePath, $content);
        $totalFilesModified++;
        echo "  MODIFIED: $filename\n";
        foreach ($changes as $change) {
            echo "    - $change\n";
        }
    }
}
