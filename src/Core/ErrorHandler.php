<?php

declare(strict_types=1);

namespace EICC\StaticForge\Core;

use EICC\StaticForge\Exceptions\CoreException;
use EICC\StaticForge\Exceptions\FeatureException;
use EICC\StaticForge\Exceptions\FileProcessingException;
use EICC\Utils\Container;
use EICC\Utils\Log;
use Throwable;

/**
 * Centralized error handling and recovery logic
 */
class ErrorHandler
{
    private Log $logger;

    /**
     * Error statistics
     * @var array{
     *   core_errors: int,
     *   feature_errors: int,
     *   file_errors: int,
     *   files_processed: int,
     *   files_failed: array<string>,
     *   features_failed: array<string>
     * }
     */
    private array $errorStats = [
        'core_errors' => 0,
        'feature_errors' => 0,
        'file_errors' => 0,
        'files_processed' => 0,
        'files_failed' => [],
        'features_failed' => [],
    ];

    public function __construct(Container $container)
    {
        $this->logger = $container->getVariable('logger');
    }

    /**
     * Handle a core system error (critical - should stop generation)
     * 
     * @param Throwable $error The error/exception to handle
     * @param array<string, mixed> $context Additional context for logging
     */
    public function handleCoreError(Throwable $error, array $context = []): void
    {
        $this->errorStats['core_errors']++;

        $logContext = array_merge(
            [
                'error_type' => 'CORE',
                'exception_class' => get_class($error),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
            ],
            $context
        );

        if ($error instanceof CoreException) {
            $logContext = array_merge($logContext, $error->getContext());
        }

        $this->logger->log(
            'CRITICAL',
            "Core system error: {$error->getMessage()}",
            $logContext
        );

        // Log stack trace for debugging
        $this->logger->log('DEBUG', "Stack trace: {$error->getTraceAsString()}");
    }

    /**
     * Handle a feature error (non-critical - can continue)
     */
    public function handleFeatureError(Throwable $error, string $featureName, string $eventName = ''): bool
    {
        $this->errorStats['feature_errors']++;

        if (!in_array($featureName, $this->errorStats['features_failed'], true)) {
            $this->errorStats['features_failed'][] = $featureName;
        }

        $logContext = [
            'error_type' => 'FEATURE',
            'feature' => $featureName,
            'event' => $eventName,
            'exception_class' => get_class($error),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
        ];

        if ($error instanceof FeatureException) {
            $logContext = array_merge($logContext, $error->getContext());
        }

        $this->logger->log(
            'ERROR',
            "Feature error in '{$featureName}' during '{$eventName}': {$error->getMessage()}",
            $logContext
        );

        // Log stack trace at debug level
        $this->logger->log('DEBUG', "Stack trace: {$error->getTraceAsString()}");

        // Feature errors are recoverable - return true to continue
        return true;
    }

    /**
     * Handle a file processing error (non-critical - can continue with other files)
     */
    public function handleFileError(Throwable $error, string $filePath, string $stage = ''): bool
    {
        $this->errorStats['file_errors']++;
        $this->errorStats['files_failed'][] = $filePath;

        $logContext = [
            'error_type' => 'FILE',
            'file' => $filePath,
            'stage' => $stage,
            'exception_class' => get_class($error),
            'file_location' => $error->getFile(),
            'line' => $error->getLine(),
        ];

        if ($error instanceof FileProcessingException) {
            $logContext = array_merge($logContext, $error->getContext());
        }

        $this->logger->log(
            'ERROR',
            "File processing error for '{$filePath}' at stage '{$stage}': {$error->getMessage()}",
            $logContext
        );

        // Log stack trace at debug level
        $this->logger->log('DEBUG', "Stack trace: {$error->getTraceAsString()}");

        // File errors are recoverable - return true to continue
        return true;
    }

    /**
     * Record successful file processing
     */
    public function recordFileSuccess(string $filePath): void
    {
        $this->errorStats['files_processed']++;
    }

    /**
     * Get error statistics
     *
     * @return array{
     *   core_errors: int,
     *   feature_errors: int,
     *   file_errors: int,
     *   files_processed: int,
     *   files_failed: array<string>,
     *   features_failed: array<string>
     * }
     */
    public function getErrorStats(): array
    {
        return $this->errorStats;
    }

    /**
     * Check if there were any critical errors
     */
    public function hasCriticalErrors(): bool
    {
        return $this->errorStats['core_errors'] > 0;
    }

    /**
     * Check if there were any non-critical errors
     */
    public function hasNonCriticalErrors(): bool
    {
        return ($this->errorStats['feature_errors'] + $this->errorStats['file_errors']) > 0;
    }

    /**
     * Log final error summary
     */
    public function logSummary(): void
    {
        $stats = $this->errorStats;

        if ($stats['core_errors'] === 0 && $stats['feature_errors'] === 0 && $stats['file_errors'] === 0) {
            $this->logger->log(
                'INFO',
                "Generation completed with no errors. Files processed: {$stats['files_processed']}"
            );
            return;
        }

        $summary = [
            'total_files_processed' => $stats['files_processed'],
            'files_failed' => count($stats['files_failed']),
            'core_errors' => $stats['core_errors'],
            'feature_errors' => $stats['feature_errors'],
            'file_errors' => $stats['file_errors'],
        ];

        if (!empty($stats['features_failed'])) {
            $summary['failed_features'] = $stats['features_failed'];
        }

        if (!empty($stats['files_failed'])) {
            $summary['failed_files'] = array_slice($stats['files_failed'], 0, 10); // Limit to first 10
            if (count($stats['files_failed']) > 10) {
                $summary['additional_failures'] = count($stats['files_failed']) - 10;
            }
        }

        $level = $stats['core_errors'] > 0 ? 'CRITICAL' : 'WARNING';

        $this->logger->log(
            $level,
            'Generation completed with errors',
            $summary
        );
    }

    /**
     * Reset error statistics
     */
    public function reset(): void
    {
        $this->errorStats = [
            'core_errors' => 0,
            'feature_errors' => 0,
            'file_errors' => 0,
            'files_processed' => 0,
            'files_failed' => [],
            'features_failed' => [],
        ];
    }
}
