<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Core;

use EICC\StaticForge\Core\FileDiscovery;
use EICC\StaticForge\Core\ExtensionRegistry;
use EICC\Utils\Container;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class FileDiscoveryFutureDateTest extends UnitTestCase
{
    private FileDiscovery $fileDiscovery;
    private ExtensionRegistry $extensionRegistry;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temp directory
        $this->tempDir = sys_get_temp_dir() . '/staticforge_future_test_' . uniqid('', true);
        mkdir($this->tempDir, 0755, true);

        // Setup container
        $this->setContainerVariable('SOURCE_DIR', $this->tempDir);
        $this->setContainerVariable('SCAN_DIRECTORIES', [$this->tempDir]);

        // Setup dependencies
        $this->extensionRegistry = new ExtensionRegistry($this->container);
        // Register extension explicitly so it picks up files
        $this->extensionRegistry->registerExtension('md');

        $this->fileDiscovery = new FileDiscovery($this->container, $this->extensionRegistry);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
        parent::tearDown();
    }

    public function testSkipsFutureFilesByDefault(): void
    {
        $futureDate = date('Y-m-d', strtotime('+1 day'));
        $pastDate = date('Y-m-d', strtotime('-1 day'));

        // Create a future file
        $content = "---\ntitle: Future Post\ndate: {$futureDate}\n---\nContent";
        file_put_contents($this->tempDir . '/future.md', $content);

        // Create a past file
        $content = "---\ntitle: Past Post\ndate: {$pastDate}\n---\nContent";
        file_put_contents($this->tempDir . '/past.md', $content);

        $this->fileDiscovery->discoverFiles();
        $files = $this->container->getVariable('discovered_files');

        // Only past file should be discovered
        $this->assertCount(1, $files);
        $this->assertEquals($this->tempDir . '/past.md', $files[0]['path']);
    }

    public function testIncludesFilesWithoutDate(): void
    {
        // Create a file without date
        $content = "---\ntitle: No Date Post\n---\nContent";
        file_put_contents($this->tempDir . '/nodate.md', $content);

        $this->fileDiscovery->discoverFiles();
        $files = $this->container->getVariable('discovered_files');

        $this->assertCount(1, $files);
        $this->assertEquals($this->tempDir . '/nodate.md', $files[0]['path']);
    }
    
    public function testIgnoresInvalidDate(): void
    {
        // Create a file with invalid date
        $content = "---\ntitle: Invalid Date Post\ndate: not-a-date\n---\nContent";
        file_put_contents($this->tempDir . '/invalid.md', $content);
        
        $this->fileDiscovery->discoverFiles();
        $files = $this->container->getVariable('discovered_files');
        
        // Should be included because strtotime returns false
        $this->assertCount(1, $files);
    }
}
