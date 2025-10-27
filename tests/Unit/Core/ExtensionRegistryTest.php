<?php

namespace EICC\StaticForge\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use EICC\StaticForge\Core\ExtensionRegistry;
use EICC\Utils\Container;
use EICC\Utils\Log;

class ExtensionRegistryTest extends TestCase
{
    private ExtensionRegistry $registry;
    private Container $container;
    private Log $logger;

    protected function setUp(): void
    {
        $this->container = new Container();
        
        // Create a temporary log file for testing
        $logFile = sys_get_temp_dir() . '/extension_registry_test.log';
        $this->logger = new Log('test', $logFile, 'INFO');
        $this->container->setVariable('logger', $this->logger);
        
        $this->registry = new ExtensionRegistry($this->container);
    }

    public function testRegisterExtension(): void
    {
        $this->registry->registerExtension('.html');
        
        $this->assertTrue($this->registry->isRegistered('.html'));
        $this->assertContains('.html', $this->registry->getRegisteredExtensions());
    }

    public function testRegisterExtensionWithoutDot(): void
    {
        $this->registry->registerExtension('html');
        
        $this->assertTrue($this->registry->isRegistered('.html'));
        $this->assertTrue($this->registry->isRegistered('html'));
    }

    public function testRegisterExtensionCaseInsensitive(): void
    {
        $this->registry->registerExtension('.HTML');
        
        $this->assertTrue($this->registry->isRegistered('.html'));
        $this->assertTrue($this->registry->isRegistered('.HTML'));
        $this->assertContains('.html', $this->registry->getRegisteredExtensions());
    }

    public function testRegisterDuplicateExtension(): void
    {
        $this->registry->registerExtension('.html');
        $this->registry->registerExtension('.html');
        
        $extensions = $this->registry->getRegisteredExtensions();
        $this->assertEquals(1, array_count_values($extensions)['.html']);
    }

    public function testCanProcessFile(): void
    {
        $this->registry->registerExtension('.html');
        $this->registry->registerExtension('.md');
        
        $this->assertTrue($this->registry->canProcess('test.html'));
        $this->assertTrue($this->registry->canProcess('test.HTML'));
        $this->assertTrue($this->registry->canProcess('/path/to/test.md'));
        $this->assertFalse($this->registry->canProcess('test.txt'));
        $this->assertFalse($this->registry->canProcess('test.php'));
    }

    public function testIsRegisteredWithUnregisteredExtension(): void
    {
        $this->assertFalse($this->registry->isRegistered('.txt'));
        $this->assertFalse($this->registry->isRegistered('php'));
    }

    public function testGetRegisteredExtensionsEmpty(): void
    {
        $extensions = $this->registry->getRegisteredExtensions();
        
        $this->assertIsArray($extensions);
        $this->assertEmpty($extensions);
    }

    public function testMultipleExtensions(): void
    {
        $this->registry->registerExtension('.html');
        $this->registry->registerExtension('.md');
        $this->registry->registerExtension('.txt');
        
        $extensions = $this->registry->getRegisteredExtensions();
        
        $this->assertCount(3, $extensions);
        $this->assertContains('.html', $extensions);
        $this->assertContains('.md', $extensions);
        $this->assertContains('.txt', $extensions);
    }
}