<?php

namespace EICC\StaticForge\Tests\Unit\Features;

use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Features\Forms\Feature;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\FeatureFactory;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class FormsFeatureTest extends UnitTestCase
{
    private Feature $feature;
    private EventManager $eventManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventManager = new EventManager();

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
        $data = $dataProp->getValue($this->container);

        // Replace the existing twig service
        $data['twig'] = function () use ($twig) {
            return $twig;
        };

        $dataProp->setValue($this->container, $data);

        $feature = (new FeatureFactory($this->container))->make(Feature::class);
        $this->assertInstanceOf(Feature::class, $feature);
        $this->feature = $feature;
        $this->feature->register($this->eventManager);
    }

    private function makeEvent(string $fileContent, string $filePath): RenderEvent
    {
        return new RenderEvent(
            name: 'RENDER',
            filePath: $filePath,
            fileUrl: '',
            metadata: [],
            extra: ['file_content' => $fileContent],
        );
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

        $event = $this->makeEvent('<h1>Contact Us</h1>{{ form("contact") }}', 'contact.html');

        $this->feature->handleRender($event);

        $this->assertStringContainsString(
            '<form action="https://api.example.com/submit?FORMID=123">Form Content</form>',
            $event->extra['file_content']
        );
        $this->assertStringNotContainsString('{{ form("contact") }}', $event->extra['file_content']);
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

        $event = $this->makeEvent('{{ form("custom") }}', 'custom.html');

        $this->feature->handleRender($event);

        $this->assertStringContainsString('<form class="custom"', $event->extra['file_content']);
    }

    public function testHandleRenderIgnoresUnknownForm(): void
    {
        $this->setContainerVariable('site_config', ['forms' => []]);

        $content = '{{ form("unknown") }}';
        $event = $this->makeEvent($content, 'test.html');

        $this->feature->handleRender($event);

        // Should remain unchanged
        $this->assertEquals($content, $event->extra['file_content']);
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

        $event = $this->makeEvent('{{ form("test") }}', 'test.html');

        $this->feature->handleRender($event);

        $this->assertStringContainsString(
            'action="https://api.example.com/submit?key=abc&amp;FORMID=123"',
            $event->extra['file_content']
        );
    }
}
