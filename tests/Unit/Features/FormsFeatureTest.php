<?php

namespace EICC\StaticForge\Tests\Unit\Features;

use EICC\StaticForge\Features\Forms\Feature;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Core\EventManager;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class FormsFeatureTest extends UnitTestCase
{
    private Feature $feature;
    private EventManager $eventManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventManager = new EventManager($this->container);

        // Mock Twig
        $loader = new ArrayLoader([
            'staticforce/_form.html.twig' => '<form action="{{ endpoint }}">Form Content</form>',
            'custom/contact.html.twig' => '<form class="custom" action="{{ endpoint }}">Custom Form</form>'
        ]);
        $twig = new Environment($loader);

        // Override the twig service in the container
        // Since Container doesn't have a remove/update method for services (only variables),
        // we use reflection to modify the protected $data property.
        $reflection = new \ReflectionClass($this->container);
        $dataProp = $reflection->getProperty('data');
        $dataProp->setAccessible(true);
        $data = $dataProp->getValue($this->container);

        // Replace the existing twig service
        $data['twig'] = function () use ($twig) {
            return $twig;
        };

        $dataProp->setValue($this->container, $data);

        $this->feature = new Feature();
        $this->feature->setContainer($this->container);
        $this->feature->register($this->eventManager);
    }

    public function testRegisterRegistersEvent(): void
    {
        $listeners = $this->eventManager->getListeners('RENDER');
        $this->assertNotEmpty($listeners);
        $this->assertCount(1, $listeners);
        $this->assertEquals([$this->feature, 'handleRender'], $listeners[0]['callback']);
    }

    public function testHandleRenderReplacesFormShortcode(): void
    {
        // Setup config
        $siteConfig = [
            'forms' => [
                'contact' => [
                    'provider_url' => 'https://api.example.com/submit',
                    'form_id' => '123'
                ]
            ]
        ];
        $this->setContainerVariable('site_config', $siteConfig);

        $content = '<h1>Contact Us</h1>{{ form("contact") }}';
        $parameters = [
            'file_content' => $content,
            'file_path' => 'contact.html'
        ];

        $result = $this->feature->handleRender($this->container, $parameters);

        $this->assertStringContainsString('<form action="https://api.example.com/submit?FORMID=123">Form Content</form>', $result['file_content']);
        $this->assertStringNotContainsString('{{ form("contact") }}', $result['file_content']);
    }

    public function testHandleRenderWithCustomTemplate(): void
    {
        $siteConfig = [
            'forms' => [
                'custom' => [
                    'provider_url' => 'https://api.example.com/submit',
                    'form_id' => '456',
                    'template' => 'contact'
                ]
            ]
        ];
        $this->setContainerVariable('site_config', $siteConfig);
        $this->setContainerVariable('TEMPLATE', 'custom');

        $content = '{{ form("custom") }}';
        $parameters = [
            'file_content' => $content,
            'file_path' => 'custom.html'
        ];

        $result = $this->feature->handleRender($this->container, $parameters);

        $this->assertStringContainsString('<form class="custom"', $result['file_content']);
    }

    public function testHandleRenderIgnoresUnknownForm(): void
    {
        $this->setContainerVariable('site_config', ['forms' => []]);

        $content = '{{ form("unknown") }}';
        $parameters = [
            'file_content' => $content,
            'file_path' => 'test.html'
        ];

        $result = $this->feature->handleRender($this->container, $parameters);

        // Should remain unchanged
        $this->assertEquals($content, $result['file_content']);
    }

    public function testHandleRenderHandlesQueryParamsInUrl(): void
    {
        $siteConfig = [
            'forms' => [
                'test' => [
                    'provider_url' => 'https://api.example.com/submit?key=abc',
                    'form_id' => '123'
                ]
            ]
        ];
        $this->setContainerVariable('site_config', $siteConfig);

        $content = '{{ form("test") }}';
        $parameters = [
            'file_content' => $content,
            'file_path' => 'test.html'
        ];

        $result = $this->feature->handleRender($this->container, $parameters);

        $this->assertStringContainsString('action="https://api.example.com/submit?key=abc&amp;FORMID=123"', $result['file_content']);
    }
}
