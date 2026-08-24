<?php

declare(strict_types=1);

namespace EICC\StaticForge\Core;

use EICC\StaticForge\Exceptions\FileProcessingException;
use EICC\Utils\Container;
use EICC\Utils\Log;

/**
 * The single write path for generated site files. Jails every write inside
 * OUTPUT_DIR via PathGuard, then writes atomically (temp file + rename) so a
 * killed process can never leave a truncated file with a fresh mtime behind
 * — which would otherwise be wrongly treated as cacheable on the next build.
 */
class OutputWriter
{
    private Container $container;
    private Log $logger;

    public function __construct(Container $container, Log $logger)
    {
        $this->container = $container;
        $this->logger = $logger;
    }

    /**
     * Write $contents to $absolutePath, which must resolve inside OUTPUT_DIR.
     * Rewriting an existing file is allowed (e.g. TemplateAssets CSS bundling).
     */
    public function write(string $absolutePath, string $contents): void
    {
        $outputDir = $this->container->getVariable('OUTPUT_DIR');
        if (!$outputDir) {
            throw new \RuntimeException('OUTPUT_DIR not set in container');
        }

        $resolvedPath = PathGuard::resolveInside($absolutePath, $outputDir);

        $outputDirPath = dirname($resolvedPath);
        if (!is_dir($outputDirPath)) {
            if (!mkdir($outputDirPath, 0755, true) && !is_dir($outputDirPath)) {
                throw new FileProcessingException(
                    "Failed to create output directory: {$outputDirPath}",
                    $resolvedPath,
                    'write'
                );
            }
        }

        $tempPath = $resolvedPath . '.tmp';

        $bytesWritten = file_put_contents($tempPath, $contents);
        if ($bytesWritten === false) {
            throw new FileProcessingException(
                "Failed to write output file: {$resolvedPath}",
                $resolvedPath,
                'write'
            );
        }

        if (!rename($tempPath, $resolvedPath)) {
            throw new FileProcessingException(
                "Failed to finalize output file: {$resolvedPath}",
                $resolvedPath,
                'write'
            );
        }

        $this->logger->log('INFO', "Written {$bytesWritten} bytes to {$resolvedPath}", [
            'output' => $resolvedPath,
            'size' => $bytesWritten,
        ]);
    }
}
