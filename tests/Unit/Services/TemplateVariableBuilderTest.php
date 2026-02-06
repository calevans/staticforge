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
        $this->setContainerVariable('site_config', [
            'site' => [
                'name' => 'My Site',
                'description' => 'Test Description'
            ],
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

    public function testBuildTemplateVariablesFromSiteConfig(): void
    {
        // Setup container variables WITHOUT SITE_NAME env var
        $this->setContainerVariable('site_config', [
            'site' => [
                'name' => 'Config Site Name',
                'tagline' => 'Config Tagline'
            ]
        ]);

        $parsedContent = [
            'title' => 'Page Title',
            'content' => 'Page Content'
        ];

        $result = $this->builder->build($parsedContent, $this->container, 'test.md');

        // Check that site_name and site_tagline are populated from site_config
        $this->assertEquals('Config Site Name', $result['site_name']);
        $this->assertEquals('Config Tagline', $result['site_tagline']);
    }

    public function testSiteConfigTakesPrecedenceOverEnvVars(): void
    {
        // Setup container variables WITH SITE_NAME env var AND site_config
        $this->setContainerVariable('SITE_NAME', 'Env Site Name');
        $this->setContainerVariable('site_config', [
            'site' => [
                'name' => 'Config Site Name'
            ]
        ]);

        $parsedContent = [
            'title' => 'Page Title',
            'content' => 'Page Content'
        ];

        $result = $this->builder->build($parsedContent, $this->container, 'test.md');

        // Config should win because it is processed first in the new logic
        $this->assertEquals('Config Site Name', $result['site_name']);
    }
}
