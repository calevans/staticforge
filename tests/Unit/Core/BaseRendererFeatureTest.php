<?php

namespace EICC\StaticForge\Tests\Unit\Core;

use EICC\StaticForge\Core\BaseRendererFeature;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\Utils\Container;

// Concrete implementation for testing abstract class
class TestRendererFeature extends BaseRendererFeature
{
    protected string $name = 'TestRenderer';

    // Expose protected method for testing
    public function testApplyDefaultMetadata(array $metadata): array
    {
        return $this->applyDefaultMetadata($metadata);
    }
}

class BaseRendererFeatureTest extends UnitTestCase
{
    private TestRendererFeature $feature;

    protected function setUp(): void
    {
        parent::setUp();
        $this->feature = new TestRendererFeature();
    }

    public function testApplyDefaultMetadata(): void
    {
        $metadata = ['custom' => 'value'];
        $result = $this->feature->testApplyDefaultMetadata($metadata);

        $this->assertEquals('base', $result['template']);
        $this->assertEquals('Untitled Page', $result['title']);
        $this->assertEquals('value', $result['custom']);
    }

    public function testApplyDefaultMetadataDoesNotOverride(): void
    {
        $metadata = [
            'template' => 'custom',
            'title' => 'My Title'
        ];
        $result = $this->feature->testApplyDefaultMetadata($metadata);

        $this->assertEquals('custom', $result['template']);
        $this->assertEquals('My Title', $result['title']);
    }


}
