<?php

declare(strict_types=1);

namespace EICC\StaticForge\Exceptions;

use Exception;

/**
 * Exception thrown when a core system component encounters a critical error
 * that should halt the entire site generation process
 */
class CoreException extends Exception
{
    private string $component;

    /**
     * Additional context for debugging
     * @var array<string, mixed>
     */
    private array $context;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message,
        string $component,
        array $context = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->component = $component;
        $this->context = $context;
    }

    public function getComponent(): string
    {
        return $this->component;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return array_merge(
            [
                'component' => $this->component,
                'message' => $this->getMessage(),
                'code' => $this->getCode(),
            ],
            $this->context
        );
    }
}
