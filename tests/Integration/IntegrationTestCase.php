<?php

namespace EICC\StaticForge\Tests\Integration;

use EICC\Utils\Container;
use PHPUnit\Framework\TestCase;

/**
 * Base class for integration tests
 * Uses physical .env files from tests/ directory
 */
abstract class IntegrationTestCase extends TestCase
{
    /**
     * Create a container using bootstrap.php with a custom env file
     *
     * @param string $envPath Path to the .env file to use
     * @return Container Configured container instance
     */
    protected function createContainer(string $envPath): Container
    {
        return require __DIR__ . '/../../src/bootstrap.php';
    }

    /**
     * Recursively remove a directory and its contents
     */
    protected function removeDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($dir);
    }
}
