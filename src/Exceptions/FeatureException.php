<?php

declare(strict_types=1);

namespace EICC\StaticForge\Exceptions;

use Exception;

/**
 * Exception thrown when a feature encounters a non-critical error
 * that should not halt the entire site generation process
 */
class FeatureException extends Exception
{
    private string $featureName;
    private string $eventName;

    public function __construct(
        string $message,
        string $featureName,
        string $eventName = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->featureName = $featureName;
        $this->eventName = $eventName;
    }

    public function getFeatureName(): string
    {
        return $this->featureName;
    }

    public function getEventName(): string
    {
        return $this->eventName;
    }

    public function getContext(): array
    {
        return [
            'feature' => $this->featureName,
            'event' => $this->eventName,
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
        ];
    }
}
