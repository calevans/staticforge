<?php

declare(strict_types=1);

namespace EICC\StaticForge\Services;

use EICC\Utils\Container;
use EICC\Utils\Log;
use Gajus\Dindent\Indenter;

abstract class BaseRendererService
{
    protected Log $logger;
    protected string $defaultTemplate = 'base';

    public function __construct(Log $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Apply default metadata to file metadata
     * Merges default values with file-specific metadata
     *
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public function applyDefaultMetadata(array $metadata): array
    {
        return array_merge([
            'template' => $this->defaultTemplate,
            'title' => 'Untitled Page',
        ], $metadata);
    }

    /**
     * Beautify HTML content using Dindent
     *
     * @param string $html Raw HTML content
     * @return string Beautified HTML content
     */
    public function beautifyHtml(string $html): string
    {
        $originalHtml = $html;

        // Protect <pre> and <textarea> tags from whitespace collapsing
        $protectedBlocks = [];
        $html = preg_replace_callback(
            '/<(pre|textarea)\b[^>]*>([\s\S]*?)<\/\1>/im',
            function ($matches) use (&$protectedBlocks) {
                $placeholder = '<!--PROTECTED_BLOCK_' . count($protectedBlocks) . '-->';
                $protectedBlocks[$placeholder] = $matches[0];
                return $placeholder;
            },
            $html
        );

        try {
            $indenter = new Indenter();
            $html = $indenter->indent($html);
        } catch (\Exception $e) {
            // If beautification fails, return original HTML
            return $originalHtml;
        }

        // Restore protected blocks
        if (!empty($protectedBlocks)) {
            $html = str_replace(array_keys($protectedBlocks), array_values($protectedBlocks), $html);
        }

        return $html;
    }

    /**
     * Generate output path for rendered file
     *
     * @param string $inputPath Absolute path to source file
     * @param Container $container
     * @param string|null $targetExtension Optional extension to replace source extension (e.g., 'html')
     * @return string Absolute path to output file
     */
    public function generateOutputPath(string $inputPath, Container $container, ?string $targetExtension = null): string
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
        } else {
            // Fallback to filename only
            $relativePath = basename($inputPath);
        }

        if ($targetExtension) {
            $extension = pathinfo($relativePath, PATHINFO_EXTENSION);
            if ($extension) {
                $relativePath = substr($relativePath, 0, -strlen($extension)) . $targetExtension;
            } else {
                $relativePath .= '.' . $targetExtension;
            }
        }

        // Build output path preserving directory structure
        // Use DIRECTORY_SEPARATOR or / consistently. The existing code used DIRECTORY_SEPARATOR in one place and / in another.
        // Let's use DIRECTORY_SEPARATOR.
        $outputPath = $outputDir . DIRECTORY_SEPARATOR . $relativePath;

        return $outputPath;
    }
}
