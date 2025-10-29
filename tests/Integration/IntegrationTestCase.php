<?php

namespace EICC\StaticForge\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Base class for integration tests
 * Handles .env file setup/teardown for command testing
 */
abstract class IntegrationTestCase extends TestCase
{
    protected string $testEnvFile;
    protected bool $createdTestEnv = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Use a SEPARATE test env file that won't conflict with production .env
        $this->testEnvFile = sys_get_temp_dir() . '/staticforge_test_' . uniqid() . '.env';
    }

    protected function tearDown(): void
    {
        // Clean up our test env file
        if ($this->createdTestEnv && file_exists($this->testEnvFile)) {
            unlink($this->testEnvFile);
        }

        // Clean up environment variable
        putenv('STATICFORGE_ENV_PATH');
        if (isset($_ENV['STATICFORGE_ENV_PATH'])) {
            unset($_ENV['STATICFORGE_ENV_PATH']);
        }

        parent::tearDown();
    }

    /**
     * Create test .env file with custom configuration
     * Returns the path to the test env file for use in Application constructor
     */
    protected function createTestEnv(array $vars): string
    {
        // Clear existing environment variables that might interfere
        $keysToUnset = ['SITE_NAME', 'SITE_BASE_URL', 'TEMPLATE', 'SOURCE_DIR', 'OUTPUT_DIR', 'TEMPLATE_DIR', 'FEATURES_DIR', 'LOG_LEVEL', 'LOG_FILE'];
        foreach ($keysToUnset as $key) {
            if (isset($_ENV[$key])) {
                unset($_ENV[$key]);
            }
            if (isset($_SERVER[$key])) {
                unset($_SERVER[$key]);
            }
            putenv($key);
        }

        $envContent = '';
        foreach ($vars as $key => $value) {
            $envContent .= "{$key}=\"{$value}\"\n";
        }
        file_put_contents($this->testEnvFile, $envContent);
        $this->createdTestEnv = true;

        // Set environment variable so commands use this test env file
        putenv('STATICFORGE_ENV_PATH=' . $this->testEnvFile);
        $_ENV['STATICFORGE_ENV_PATH'] = $this->testEnvFile;

        return $this->testEnvFile;
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
