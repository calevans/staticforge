<?php

namespace EICC\StaticForge\Tests\Unit\Environment;

use PHPUnit\Framework\TestCase;
use EICC\Utils\Container;
use EICC\StaticForge\Environment\EnvironmentLoader;
use InvalidArgumentException;

/**
 * @backupGlobals enabled
 */
class EnvironmentLoaderTest extends TestCase
{
    private Container $container;
    private EnvironmentLoader $loader;
    private string $testEnvFile;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->loader = new EnvironmentLoader($this->container);
        $this->testEnvFile = sys_get_temp_dir() . '/test.env';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testEnvFile)) {
            unlink($this->testEnvFile);
        }
    }

    public function testLoadValidEnvironmentFile(): void
    {
        $envContent = <<<ENV
SITE_NAME="Test Site"
SITE_BASE_URL="https://test.com"
SOURCE_DIR="content"
OUTPUT_DIR="public"
TEMPLATE_DIR="templates"
FEATURES_DIR="src/Features"
ENV;

        file_put_contents($this->testEnvFile, $envContent);

        $this->loader->load($this->testEnvFile);

        $this->assertEquals('Test Site', $this->container->getVariable('SITE_NAME'));
        $this->assertEquals('https://test.com', $this->container->getVariable('SITE_BASE_URL'));
        $this->assertEquals('content', $this->container->getVariable('SOURCE_DIR'));
    }

    public function testThrowsExceptionForMissingFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Environment file not found: nonexistent.env');

        $this->loader->load('nonexistent.env');
    }

    public function testThrowsExceptionForMissingRequiredVariables(): void
    {
        $envContent = <<<ENV
SITE_NAME="Test Site"
# Missing other required variables
ENV;

        file_put_contents($this->testEnvFile, $envContent);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required environment variables:');

        // Clear any existing environment variables first
        foreach (['SITE_BASE_URL', 'SOURCE_DIR', 'OUTPUT_DIR', 'TEMPLATE_DIR', 'FEATURES_DIR'] as $var) {
            unset($_ENV[$var]);
        }

        $this->loader->load($this->testEnvFile);
    }

    public function testAddRequiredVariable(): void
    {
        $this->loader->addRequiredVariable('CUSTOM_VAR');

        $envContent = <<<ENV
SITE_NAME="Test Site"
SITE_BASE_URL="https://test.com"
SOURCE_DIR="content"
OUTPUT_DIR="public"
TEMPLATE_DIR="templates"
FEATURES_DIR="src/Features"
# Missing CUSTOM_VAR
ENV;

        file_put_contents($this->testEnvFile, $envContent);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CUSTOM_VAR');

        $this->loader->load($this->testEnvFile);
    }
}