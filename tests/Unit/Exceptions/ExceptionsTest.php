<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Exceptions;

use EICC\StaticForge\Exceptions\CoreException;
use EICC\StaticForge\Exceptions\FeatureException;
use EICC\StaticForge\Exceptions\FileProcessingException;
use PHPUnit\Framework\TestCase;

class ExceptionsTest extends TestCase
{
    public function testCoreExceptionStoresComponent(): void
    {
        $exception = new CoreException('Test message', 'FileDiscovery', ['key' => 'value']);

        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals('FileDiscovery', $exception->getComponent());

        $context = $exception->getContext();
        $this->assertEquals('FileDiscovery', $context['component']);
        $this->assertEquals('Test message', $context['message']);
        $this->assertEquals('value', $context['key']);
    }

    public function testCoreExceptionWithPreviousException(): void
    {
        $previous = new \Exception('Previous error');
        $exception = new CoreException('Test message', 'Application', [], 500, $previous);

        $this->assertSame($previous, $exception->getPrevious());
        $this->assertEquals(500, $exception->getCode());
    }

    public function testFeatureExceptionStoresFeatureAndEvent(): void
    {
        $exception = new FeatureException('Feature failed', 'MenuBuilder', 'POST_GLOB');

        $this->assertEquals('Feature failed', $exception->getMessage());
        $this->assertEquals('MenuBuilder', $exception->getFeatureName());
        $this->assertEquals('POST_GLOB', $exception->getEventName());

        $context = $exception->getContext();
        $this->assertEquals('MenuBuilder', $context['feature']);
        $this->assertEquals('POST_GLOB', $context['event']);
        $this->assertEquals('Feature failed', $context['message']);
    }

    public function testFeatureExceptionWithoutEvent(): void
    {
        $exception = new FeatureException('Feature failed', 'Tags');

        $this->assertEquals('', $exception->getEventName());
    }

    public function testFileProcessingExceptionStoresFileAndStage(): void
    {
        $exception = new FileProcessingException(
            'Failed to render',
            '/content/blog/post.md',
            'render'
        );

        $this->assertEquals('Failed to render', $exception->getMessage());
        $this->assertEquals('/content/blog/post.md', $exception->getFilePath());
        $this->assertEquals('render', $exception->getProcessingStage());

        $context = $exception->getContext();
        $this->assertEquals('/content/blog/post.md', $context['file']);
        $this->assertEquals('render', $context['stage']);
        $this->assertEquals('Failed to render', $context['message']);
    }

    public function testFileProcessingExceptionWithoutStage(): void
    {
        $exception = new FileProcessingException('Error', '/file.md');

        $this->assertEquals('', $exception->getProcessingStage());
    }

    public function testAllExceptionsAreInstanceOfException(): void
    {
        $coreException = new CoreException('Error', 'Component');
        $featureException = new FeatureException('Error', 'Feature');
        $fileException = new FileProcessingException('Error', '/file.md');

        $this->assertInstanceOf(\Exception::class, $coreException);
        $this->assertInstanceOf(\Exception::class, $featureException);
        $this->assertInstanceOf(\Exception::class, $fileException);
    }
}
