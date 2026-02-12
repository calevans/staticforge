<?php

declare(strict_types=1);

namespace EICC\StaticForge\Core;

use EICC\Utils\Container;
use EICC\Utils\Log;

/**
 * Registry for file extensions that can be processed by renderer features
 */
class ExtensionRegistry
{
    private Log $logger;

    /**
     * Registered file extensions
     * @var array<string>
     */
    private array $extensions = [];

    public function __construct(Container $container)
    {
        $this->logger = $container->get('logger');
    }

    /**
     * Register a file extension that can be processed
     */
    public function registerExtension(string $extension): void
    {
        if (!str_starts_with($extension, '.')) {
            $extension = '.' . $extension;
        }

        $extension = strtolower($extension);

        if (!in_array($extension, $this->extensions)) {
            $this->extensions[] = $extension;
            $this->logger->log('INFO', "Registered extension: {$extension}");
        }
    }

    /**
     * Check if an extension is registered
     */
    public function isRegistered(string $extension): bool
    {
        if (!str_starts_with($extension, '.')) {
            $extension = '.' . $extension;
        }

        return in_array(strtolower($extension), $this->extensions);
    }

    /**
     * Get all registered extensions
     *
     * @return array<string>
     */
    public function getRegisteredExtensions(): array
    {
        return $this->extensions;
    }

    /**
     * Check if a file path has a registered extension
     */
    public function canProcess(string $filePath): bool
    {
        $extension = '.' . strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return $this->isRegistered($extension);
    }
}
