<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Core;

use EICC\StaticForge\Core\ErrorHandler;
use EICC\StaticForge\Exceptions\CoreException;
use EICC\StaticForge\Exceptions\FeatureException;
use EICC\StaticForge\Exceptions\FileProcessingException;
use EICC\Utils\Container;
use EICC\Utils\Log;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class ErrorHandlerTest extends UnitTestCase
{
    private ErrorHandler $errorHandler;
    private string $logFile;

    protected function setUp(): void
    {
        parent::setUp();

        // Use the log file from bootstrap configuration
        $this->logFile = $this->container->getVariable('LOG_FILE');

        // Clear the log file before each test
        if (file_exists($this->logFile)) {
            file_put_contents($this->logFile, '');
        }

        $this->errorHandler = new ErrorHandler($this->container);
    }

    protected function tearDown(): void
    {
        // Clean up log file after tests
        if (file_exists($this->logFile)) {
            file_put_contents($this->logFile, '');
        }
        parent::tearDown();
    }

    public function testHandleCoreError(): void
    {
        $exception = new CoreException('Test core error', 'TestComponent', ['key' => 'value']);

        $this->errorHandler->handleCoreError($exception);

        $stats = $this->errorHandler->getErrorStats();
        $this->assertEquals(1, $stats['core_errors']);
        $this->assertEquals(0, $stats['feature_errors']);
        $this->assertEquals(0, $stats['file_errors']);
        $this->assertTrue($this->errorHandler->hasCriticalErrors());
    }

    public function testHandleFeatureError(): void
    {
        $exception = new FeatureException('Test feature error', 'TestFeature', 'TEST_EVENT');

        $result = $this->errorHandler->handleFeatureError($exception, 'TestFeature', 'TEST_EVENT');

        $this->assertTrue($result); // Should return true to continue processing

        $stats = $this->errorHandler->getErrorStats();
        $this->assertEquals(0, $stats['core_errors']);
        $this->assertEquals(1, $stats['feature_errors']);
        $this->assertContains('TestFeature', $stats['features_failed']);
        $this->assertTrue($this->errorHandler->hasNonCriticalErrors());
    }

    public function testHandleFileError(): void
    {
        $exception = new FileProcessingException('Test file error', '/path/to/file.md', 'render');

        $result = $this->errorHandler->handleFileError($exception, '/path/to/file.md', 'render');

        $this->assertTrue($result); // Should return true to continue processing

        $stats = $this->errorHandler->getErrorStats();
        $this->assertEquals(0, $stats['core_errors']);
        $this->assertEquals(1, $stats['file_errors']);
        $this->assertContains('/path/to/file.md', $stats['files_failed']);
        $this->assertTrue($this->errorHandler->hasNonCriticalErrors());
    }

    public function testRecordFileSuccess(): void
    {
        $this->errorHandler->recordFileSuccess('/path/to/file1.md');
        $this->errorHandler->recordFileSuccess('/path/to/file2.md');

        $stats = $this->errorHandler->getErrorStats();
        $this->assertEquals(2, $stats['files_processed']);
    }

    public function testGetErrorStats(): void
    {
        $this->errorHandler->recordFileSuccess('/file1.md');
        $this->errorHandler->handleFileError(
            new FileProcessingException('Error', '/file2.md', 'render'),
            '/file2.md',
            'render'
        );
        $this->errorHandler->handleFeatureError(
            new FeatureException('Error', 'Feature1', 'EVENT'),
            'Feature1',
            'EVENT'
        );

        $stats = $this->errorHandler->getErrorStats();

        $this->assertEquals(0, $stats['core_errors']);
        $this->assertEquals(1, $stats['feature_errors']);
        $this->assertEquals(1, $stats['file_errors']);
        $this->assertEquals(1, $stats['files_processed']);
        $this->assertCount(1, $stats['files_failed']);
        $this->assertCount(1, $stats['features_failed']);
    }

    public function testHasCriticalErrors(): void
    {
        $this->assertFalse($this->errorHandler->hasCriticalErrors());

        $this->errorHandler->handleCoreError(new CoreException('Error', 'Component'));

        $this->assertTrue($this->errorHandler->hasCriticalErrors());
    }

    public function testHasNonCriticalErrors(): void
    {
        $this->assertFalse($this->errorHandler->hasNonCriticalErrors());

        $this->errorHandler->handleFileError(
            new FileProcessingException('Error', '/file.md', 'render'),
            '/file.md',
            'render'
        );

        $this->assertTrue($this->errorHandler->hasNonCriticalErrors());
    }

    public function testReset(): void
    {
        $this->errorHandler->recordFileSuccess('/file1.md');
        $this->errorHandler->handleFileError(
            new FileProcessingException('Error', '/file2.md', 'render'),
            '/file2.md',
            'render'
        );

        $this->errorHandler->reset();

        $stats = $this->errorHandler->getErrorStats();
        $this->assertEquals(0, $stats['core_errors']);
        $this->assertEquals(0, $stats['feature_errors']);
        $this->assertEquals(0, $stats['file_errors']);
        $this->assertEquals(0, $stats['files_processed']);
        $this->assertEmpty($stats['files_failed']);
        $this->assertEmpty($stats['features_failed']);
    }

    public function testLogSummaryWithNoErrors(): void
    {
        $this->errorHandler->recordFileSuccess('/file1.md');
        $this->errorHandler->recordFileSuccess('/file2.md');

        $this->errorHandler->logSummary();

        $this->assertFileExists($this->logFile);
        $logContents = file_get_contents($this->logFile);
        $this->assertStringContainsString('no errors', $logContents);
    }

    public function testLogSummaryWithErrors(): void
    {
        $this->errorHandler->handleFileError(
            new FileProcessingException('Error', '/file.md', 'render'),
            '/file.md',
            'render'
        );

        $this->errorHandler->logSummary();

        $this->assertFileExists($this->logFile);
        $logContents = file_get_contents($this->logFile);
        $this->assertStringContainsString('with errors', $logContents);
    }

    public function testMultipleFeatureErrorsOnlyCountsFeatureOnce(): void
    {
        $this->errorHandler->handleFeatureError(
            new FeatureException('Error 1', 'TestFeature', 'EVENT1'),
            'TestFeature',
            'EVENT1'
        );
        $this->errorHandler->handleFeatureError(
            new FeatureException('Error 2', 'TestFeature', 'EVENT2'),
            'TestFeature',
            'EVENT2'
        );

        $stats = $this->errorHandler->getErrorStats();
        $this->assertEquals(2, $stats['feature_errors']);
        $this->assertCount(1, $stats['features_failed']); // Only one unique feature
    }

    public function testHandleGenericExceptions(): void
    {
        $exception = new \Exception('Generic error');

        $this->errorHandler->handleCoreError($exception);

        $stats = $this->errorHandler->getErrorStats();
        $this->assertEquals(1, $stats['core_errors']);
    }

    public function testContextIsLoggedForCustomExceptions(): void
    {
        $exception = new CoreException(
            'Test error',
            'FileDiscovery',
            ['content_dir' => 'content', 'file_count' => 0]
        );

        $this->errorHandler->handleCoreError($exception, ['stage' => 'discovery']);

        $this->assertFileExists($this->logFile);
        $logContents = file_get_contents($this->logFile);
        $this->assertStringContainsString('FileDiscovery', $logContents);
        $this->assertStringContainsString('discovery', $logContents);
    }
}
