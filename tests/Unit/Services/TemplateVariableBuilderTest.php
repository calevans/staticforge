<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Services;

use EICC\StaticForge\Services\TemplateVariableBuilder;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\Utils\Container;

class TemplateVariableBuilderTest extends UnitTestCase
{
    private TemplateVariableBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new TemplateVariableBuilder();
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

        $result = $this->builder->build($parsedContent, $this->container, 'test.md');

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
