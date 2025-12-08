<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\CacheBuster\Services;

use EICC\StaticForge\Features\CacheBuster\Services\CacheBusterService;
use EICC\Utils\Log;
use PHPUnit\Framework\TestCase;

class CacheBusterServiceTest extends TestCase
{
    public function testGenerateBuildId(): void
    {
        $logger = $this->createMock(Log::class);
        $service = new CacheBusterService($logger);

        $buildId = $service->generateBuildId();

        $this->assertIsString($buildId);
        $this->assertNotEmpty($buildId);
        $this->assertTrue(is_numeric($buildId));
    }
}
