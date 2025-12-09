<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\MediaInspect\Services;

use EICC\StaticForge\Features\MediaInspect\Services\MediaInspector;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use RuntimeException;

class MediaInspectorTest extends UnitTestCase
{
    private MediaInspector $inspector;
    private string $testFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inspector = new MediaInspector();

        // Create a dummy file for testing basic file operations
        // Note: We can't easily mock getID3 without a complex setup or a real media file,
        // so we'll test the error handling and basic file existence checks.
        $this->testFile = sys_get_temp_dir() . '/test_media_' . uniqid() . '.mp3';
        file_put_contents($this->testFile, 'dummy content');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testFile)) {
            unlink($this->testFile);
        }
        parent::tearDown();
    }

    public function testInspectThrowsExceptionForNonExistentFile(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File not found');

        $this->inspector->inspect('/path/to/non/existent/file.mp3');
    }

    public function testInspectReturnsBasicInfoForDummyFile(): void
    {
        // Since it's a dummy file, getID3 might return errors or basic info depending on config.
        // We expect it to at least run without crashing on file access.
        // However, getID3 usually returns an error key for invalid files.

        try {
            $result = $this->inspector->inspect($this->testFile);

            // If it succeeds (unlikely for text content in mp3 extension), check structure
            $this->assertArrayHasKey('size', $result);
            $this->assertArrayHasKey('type', $result);
            $this->assertArrayHasKey('duration', $result);
        } catch (RuntimeException $e) {
            // If it fails analysis (expected for dummy content), verify the message
            $this->assertStringContainsString('Failed to analyze file', $e->getMessage());
        }
    }
}
