<?php

declare(strict_types=1);

/**
 * Migration script to update templates from v={{ build_id }} to {{ cache_buster }}
 *
 * Usage: php migrations/migrate_cache_buster.php
 */

$templateDir = __DIR__ . '/../templates';

if (!is_dir($templateDir)) {
    echo "Templates directory not found at $templateDir\n";
    exit(1);
}

echo "Scanning for templates in $templateDir...\n";

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($templateDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

$count = 0;

foreach ($iterator as $file) {
    // Only process twig files or html files that might be templates
    if (!in_array($file->getExtension(), ['twig', 'html', 'md'])) {
        continue;
    }

    $content = file_get_contents($file->getPathname());

    // Look for v={{ build_id }} with flexible whitespace
    // Matches: v={{build_id}}, v={{ build_id }}, v = {{ build_id }}
    $pattern = '/v\s*=\s*\{\{\s*build_id\s*\}\}/';

    if (preg_match($pattern, $content)) {
        $newContent = preg_replace($pattern, '{{ cache_buster }}', $content);

        if ($newContent !== $content) {
            file_put_contents($file->getPathname(), $newContent);
            echo "Migrated: " . $file->getPathname() . "\n";
            $count++;
        }
    }
}

echo "Migration complete. Modified $count files.\n";
