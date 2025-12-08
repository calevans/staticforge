<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\CacheBuster\Services;

use EICC\Utils\Log;

class CacheBusterService
{
    private Log $logger;

    public function __construct(Log $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Generate a unique build ID for cache busting
     */
    public function generateBuildId(): string
    {
        $buildId = (string)time();
        $this->logger->log('INFO', "CacheBuster: Generated build_id {$buildId}");

        return $buildId;
    }
}
