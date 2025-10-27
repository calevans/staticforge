<?php

namespace EICC\StaticForge\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use EICC\StaticForge\Core\Bootstrap;
use EICC\Utils\Container;
use InvalidArgumentException;

/**
 * @backupGlobals enabled
 */
class BootstrapTest extends TestCase
{
    private Bootstrap $bootstrap;
    private string $testEnvFile;

    protected function setUp(): void
    {
        $this->bootstrap = new Bootstrap();
        $this->testEnvFile = sys_get_temp_dir() . '/bootstrap_test.env';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testEnvFile)) {
            unlink($this->testEnvFile);
        }

        // Clean up environment variables to prevent cross-test pollution
        foreach (['SITE_NAME', 'SITE_BASE_URL', 'SOURCE_DIR', 'OUTPUT_DIR', 'TEMPLATE_DIR', 'FEATURES_DIR'] as $var) {
            if (isset($_ENV[$var])) {
                unset($_ENV[$var]);
            }
            if (getenv($var) !== false) {
                putenv($var);
            }
        }
    }

    public function testInitializeWithValidEnvironment(): void
    {
        $envContent = <<<ENV
SITE_NAME="Bootstrap Test"
SITE_BASE_URL="https://bootstrap.com"
SOURCE_DIR="content"
OUTPUT_DIR="public"
TEMPLATE_DIR="templates"
FEATURES_DIR="src/Features"
ENV;

        file_put_contents($this->testEnvFile, $envContent);

        $container = $this->bootstrap->initialize($this->testEnvFile);

        $this->assertInstanceOf(Container::class, $container);
        $this->assertEquals('Bootstrap Test', $container->getVariable('SITE_NAME'));
        $this->assertSame($container, $container->get('container'));
    }

    public function testGetContainer(): void
    {
        $container = $this->bootstrap->getContainer();
        $this->assertInstanceOf(Container::class, $container);
    }

    public function testInitializeWithInvalidEnvironmentThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->bootstrap->initialize('nonexistent.env');
    }
}