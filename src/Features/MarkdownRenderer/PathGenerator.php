<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\MarkdownRenderer;

use EICC\Utils\Container;

class PathGenerator
{
    /**
     * Generate output file path, changing .md to .html
     */
    public function generateOutputPath(string $inputPath, Container $container): string
    {
        $sourceDir = $container->getVariable('SOURCE_DIR');
        if (!$sourceDir) {
            throw new \RuntimeException('SOURCE_DIR not set in container');
        }
        $outputDir = $container->getVariable('OUTPUT_DIR');
        if (!$outputDir) {
            throw new \RuntimeException('OUTPUT_DIR not set in container');
        }

        // Normalize paths for comparison (handle both real and virtual filesystems)
        $normalizedSourceDir = rtrim($sourceDir, DIRECTORY_SEPARATOR);
        $normalizedInputPath = $inputPath;

        // Check if input path starts with source directory
        if (strpos($normalizedInputPath, $normalizedSourceDir) === 0) {
            // Get path relative to source directory
            $relativePath = substr($normalizedInputPath, strlen($normalizedSourceDir) + 1);
            // Change .md extension to .html
            $relativePath = preg_replace('/\.md$/', '.html', $relativePath);
        } else {
            // Fallback to filename only
            $relativePath = basename($inputPath);
            $relativePath = preg_replace('/\.md$/', '.html', $relativePath);
        }

        // Build output path preserving directory structure
        $outputPath = $outputDir . '/' . $relativePath;

        return $outputPath;
    }
}
