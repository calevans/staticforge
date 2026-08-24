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

    public function testBuildExcludesSecretsFromContainer(): void
    {
        // Mirrors what src/bootstrap.php does with every .env value: dumps it
        // onto the container as a plain variable via setVariable(). Before
        // the allowlist, TemplateVariableBuilder exposed all of it to Twig.
        $this->setContainerVariable('SFTP_PASSWORD', 'super-secret-password');
        $this->setContainerVariable('SFTP_PRIVATE_KEY_PATH', '/home/user/.ssh/id_rsa');
        $this->setContainerVariable('GOOGLE_ANALYTICS_ID', 'UA-SECRET-123');
        $this->setContainerVariable('app_root', '/var/www/staticforge');
        $this->setContainerVariable('discovered_files', [['path' => '/content/x.md']]);

        $result = $this->builder->build(['title' => 'T', 'content' => 'C'], $this->container, 'test.md');

        $this->assertArrayNotHasKey('SFTP_PASSWORD', $result);
        $this->assertArrayNotHasKey('SFTP_PRIVATE_KEY_PATH', $result);
        $this->assertArrayNotHasKey('GOOGLE_ANALYTICS_ID', $result);
        $this->assertArrayNotHasKey('app_root', $result);
        $this->assertArrayNotHasKey('discovered_files', $result);
    }

    public function testBuildKeepsVariablesTemplatesActuallyUse(): void
    {
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.com/');
        $this->setContainerVariable('cache_buster', 'sfcb=abc123');
        $this->setContainerVariable('features', ['MenuBuilder' => ['html' => ['1' => '<ul></ul>']]]);
        $this->setContainerVariable('menu1', '<ul><li>Home</li></ul>');
        $this->setContainerVariable('menu_top', '<ul><li>Top</li></ul>');

        $result = $this->builder->build(['title' => 'T', 'content' => 'C'], $this->container, 'test.md');

        $this->assertSame('https://example.com/', $result['site_base_url']);
        $this->assertSame('sfcb=abc123', $result['cache_buster']);
        $this->assertSame(['MenuBuilder' => ['html' => ['1' => '<ul></ul>']]], $result['features']);
        $this->assertSame('<ul><li>Home</li></ul>', $result['menu1']);
        $this->assertSame('<ul><li>Top</li></ul>', $result['menu_top']);
    }
}
