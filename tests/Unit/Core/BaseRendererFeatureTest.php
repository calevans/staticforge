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
    public function testBuildTemplateVariables(array $parsedContent, Container $container, string $sourceFile = ''): array
    {
        return $this->buildTemplateVariables($parsedContent, $container, $sourceFile);
    }

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

    public function testBuildTemplateVariablesMergesSources(): void
    {
        // Setup container variables
        $this->setContainerVariable('SITE_NAME', 'My Site');
        $this->setContainerVariable('site_config', [
            'site' => ['description' => 'Test Description'],
            'menu' => ['top' => []]
        ]);

        $parsedContent = [
            'title' => 'Page Title',
            'content' => 'Page Content',
            'metadata' => [
                'author' => 'Me',
                'description' => 'Page Description' // Should override site config
            ]
        ];

        $result = $this->feature->testBuildTemplateVariables($parsedContent, $this->container, 'test.md');

        // Check env var normalization
        $this->assertEquals('My Site', $result['site_name']);

        // Check site config flattening
        $this->assertEquals(['top' => []], $result['menu']);

        // Check content variables
        $this->assertEquals('Page Title', $result['title']);
        $this->assertEquals('Page Content', $result['content']);
        $this->assertEquals('test.md', $result['source_file']);

        // Check metadata merge and override
        $this->assertEquals('Me', $result['author']);
        $this->assertEquals('Page Description', $result['description']);
    }
}
