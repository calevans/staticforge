<?php

declare(strict_types=1);

namespace EICC\StaticForge\Exceptions;

use Exception;

/**
 * Exception thrown when processing a specific file fails
 * File processing can continue with other files
 */
class FileProcessingException extends Exception
{
    private string $filePath;
    private string $processingStage;

    public function __construct(
        string $message,
        string $filePath,
        string $processingStage = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->filePath = $filePath;
        $this->processingStage = $processingStage;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getProcessingStage(): string
    {
        return $this->processingStage;
    }

    public function getContext(): array
    {
        return [
            'file' => $this->filePath,
            'stage' => $this->processingStage,
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
        ];
    }
}
