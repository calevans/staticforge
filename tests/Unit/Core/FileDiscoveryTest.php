<?php

namespace EICC\StaticForge\Tests\Unit\Core;

use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Core\FileDiscovery;
use EICC\StaticForge\Core\ExtensionRegistry;
use EICC\Utils\Container;
use EICC\Utils\Log;

class FileDiscoveryTest extends UnitTestCase
{
    private FileDiscovery $fileDiscovery;
    private ExtensionRegistry $extensionRegistry;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extensionRegistry = new ExtensionRegistry($this->container);

        // Create temporary directory for test files
        $this->tempDir = sys_get_temp_dir() . '/staticforge_discovery_' . uniqid();
        mkdir($this->tempDir, 0777, true);

        $this->fileDiscovery = new FileDiscovery($this->container, $this->extensionRegistry);
    }

    protected function tearDown(): void
    {
        // Clean up temporary directory
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    public function testDiscoverFilesWithSourceDir(): void
    {
        $this->setContainerVariable('SOURCE_DIR', $this->tempDir);
        $this->extensionRegistry->registerExtension('.html');

        $this->createTestFile('test1.html', '<h1>Test 1</h1>');
        $this->createTestFile('test2.html', '<h1>Test 2</h1>');
        $this->createTestFile('ignored.txt', 'This should be ignored');

        $this->fileDiscovery->discoverFiles();

        $discoveredFiles = $this->container->getVariable('discovered_files');
        $this->assertIsArray($discoveredFiles);
        $this->assertCount(2, $discoveredFiles);
    }

    public function testDiscoverFilesWithScanDirectories(): void
    {
        $this->setContainerVariable('SCAN_DIRECTORIES', [$this->tempDir]);
        $this->extensionRegistry->registerExtension('.md');

        $this->createTestFile('test.md', '# Test');
        $this->createTestFile('ignored.html', '<h1>Ignored</h1>');

        $this->fileDiscovery->discoverFiles();

        $discoveredFiles = $this->container->getVariable('discovered_files');
        $this->assertCount(1, $discoveredFiles);
        $this->assertStringContainsString('test.md', $discoveredFiles[0]['path']);
    }

    public function testDiscoverFilesWithMultipleDirectories(): void
    {
        $dir1 = $this->tempDir . '/dir1';
        $dir2 = $this->tempDir . '/dir2';
        mkdir($dir1, 0777, true);
        mkdir($dir2, 0777, true);

        $this->setContainerVariable('SCAN_DIRECTORIES', [$dir1, $dir2]);
        $this->extensionRegistry->registerExtension('.html');

        file_put_contents($dir1 . '/test1.html', '<h1>Test 1</h1>');
        file_put_contents($dir2 . '/test2.html', '<h1>Test 2</h1>');

        $this->fileDiscovery->discoverFiles();

        $discoveredFiles = $this->container->getVariable('discovered_files');
        $this->assertCount(2, $discoveredFiles);
    }

    public function testDiscoverFilesWithNonexistentDirectory(): void
    {
        $this->setContainerVariable('SOURCE_DIR', '/nonexistent/path');
        $this->extensionRegistry->registerExtension('.html');

        $this->fileDiscovery->discoverFiles();

        $discoveredFiles = $this->container->getVariable('discovered_files');
        $this->assertIsArray($discoveredFiles);
        $this->assertEmpty($discoveredFiles);
    }

    public function testDiscoverFilesWithNoRegisteredExtensions(): void
    {
        $this->setContainerVariable('SOURCE_DIR', $this->tempDir);
        // No extensions registered

        $this->createTestFile('test.html', '<h1>Test</h1>');

        $this->fileDiscovery->discoverFiles();

        $discoveredFiles = $this->container->getVariable('discovered_files');
        $this->assertEmpty($discoveredFiles);
    }

    public function testDiscoverFilesInNestedDirectories(): void
    {
        $this->setContainerVariable('SOURCE_DIR', $this->tempDir);
        $this->extensionRegistry->registerExtension('.html');

        mkdir($this->tempDir . '/subdir', 0777, true);
        mkdir($this->tempDir . '/subdir/deeper', 0777, true);

        $this->createTestFile('root.html', '<h1>Root</h1>');
        $this->createTestFile('subdir/sub.html', '<h1>Sub</h1>');
        $this->createTestFile('subdir/deeper/deep.html', '<h1>Deep</h1>');

        $this->fileDiscovery->discoverFiles();

        $discoveredFiles = $this->container->getVariable('discovered_files');
        $this->assertCount(3, $discoveredFiles);
    }

    public function testDiscoverFilesWithMultipleExtensions(): void
    {
        $this->setContainerVariable('SOURCE_DIR', $this->tempDir);
        $this->extensionRegistry->registerExtension('.html');
        $this->extensionRegistry->registerExtension('.md');
        $this->extensionRegistry->registerExtension('.txt');

        $this->createTestFile('test.html', '<h1>Test</h1>');
        $this->createTestFile('test.md', '# Test');
        $this->createTestFile('test.txt', 'Test');
        $this->createTestFile('ignored.php', '<?php echo "ignored"; ?>');

        $this->fileDiscovery->discoverFiles();

        $discoveredFiles = $this->container->getVariable('discovered_files');
        $this->assertCount(3, $discoveredFiles);
    }

    private function createTestFile(string $relativePath, string $content): string
    {
        $fullPath = $this->tempDir . '/' . $relativePath;
        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($fullPath, $content);

        return $fullPath;
    }

    // removeDirectory is now provided by UnitTestCase
}
