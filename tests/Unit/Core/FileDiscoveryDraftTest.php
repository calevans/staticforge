<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Core;

use EICC\StaticForge\Core\FileDiscovery;
use EICC\StaticForge\Core\ExtensionRegistry;
use EICC\Utils\Container;
use EICC\Utils\Log;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class FileDiscoveryDraftTest extends UnitTestCase
{
    private FileDiscovery $fileDiscovery;
    private ExtensionRegistry $extensionRegistry;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temp directory
        $this->tempDir = sys_get_temp_dir() . '/staticforge_draft_test_' . uniqid('', true);
        mkdir($this->tempDir, 0755, true);

        // Setup container
        $this->setContainerVariable('SOURCE_DIR', $this->tempDir);
        $this->setContainerVariable('SCAN_DIRECTORIES', [$this->tempDir]);

        // Setup dependencies
        $this->extensionRegistry = new ExtensionRegistry($this->container);
        $this->extensionRegistry->registerExtension('md');

        $this->fileDiscovery = new FileDiscovery($this->container, $this->extensionRegistry);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->recursiveRemoveDirectory($this->tempDir);
        }
    }

    private function recursiveRemoveDirectory(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testSkipsDraftFilesByDefault(): void
    {
        // Create a draft file
        $content = "---\ntitle: Draft Post\ndraft: true\n---\nContent";
        file_put_contents($this->tempDir . '/draft.md', $content);

        // Create a published file
        $content = "---\ntitle: Published Post\ndraft: false\n---\nContent";
        file_put_contents($this->tempDir . '/published.md', $content);

        $this->fileDiscovery->discoverFiles();
        $files = $this->container->getVariable('discovered_files');

        $this->assertCount(1, $files);
        $this->assertEquals($this->tempDir . '/published.md', $files[0]['path']);
    }

    public function testIncludesDraftFilesWhenConfigured(): void
    {
        $this->setContainerVariable('SHOW_DRAFTS', true);

        // Create a draft file
        $content = "---\ntitle: Draft Post\ndraft: true\n---\nContent";
        file_put_contents($this->tempDir . '/draft.md', $content);

        $this->fileDiscovery->discoverFiles();
        $files = $this->container->getVariable('discovered_files');

        $this->assertCount(1, $files);
        $this->assertEquals($this->tempDir . '/draft.md', $files[0]['path']);
    }

    public function testIncludesFilesWithoutDraftStatus(): void
    {
        // Create a file without draft status
        $content = "---\ntitle: Normal Post\n---\nContent";
        file_put_contents($this->tempDir . '/normal.md', $content);

        $this->fileDiscovery->discoverFiles();
        $files = $this->container->getVariable('discovered_files');

        $this->assertCount(1, $files);
        $this->assertEquals($this->tempDir . '/normal.md', $files[0]['path']);
    }
}
