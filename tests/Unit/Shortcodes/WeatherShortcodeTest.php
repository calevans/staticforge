<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Shortcodes;

use EICC\StaticForge\Shortcodes\WeatherShortcode;
use EICC\StaticForge\Services\TemplateRenderer;
use EICC\StaticForge\Services\TemplateVariableBuilder;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class WeatherShortcodeTest extends UnitTestCase
{
    private string $templateDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->templateDir = sys_get_temp_dir() . '/staticforge_weather_templates_' . uniqid();
        mkdir($this->templateDir . '/test/shortcodes', 0755, true);

        $template = <<<'EOT'
Location: {{ location }}
Temp: {{ temperature }} {{ unit }}
Wind: {{ windspeed }}
Condition: {{ condition }}
EOT;

        file_put_contents($this->templateDir . '/test/shortcodes/weather.twig', $template);

        $this->setContainerVariable('TEMPLATE_DIR', $this->templateDir);
        $this->setContainerVariable('TEMPLATE', 'test');
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.test');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->templateDir);
        $this->clearWeatherCache();
        parent::tearDown();
    }

    public function testWeatherShortcodeRendersFromZip(): void
    {
        $zipResponse = new MockResponse(json_encode([
            'places' => [[
                'latitude' => '26.7',
                'longitude' => '-80.1',
                'place name' => 'West Palm Beach',
                'state abbreviation' => 'FL',
            ]],
        ]));

        $weatherResponse = new MockResponse(json_encode([
            'current_weather' => [
                'temperature' => 20,
                'windspeed' => 5,
                'weathercode' => 1,
            ],
        ]));

        $client = new MockHttpClient([$zipResponse, $weatherResponse]);

        $shortcode = $this->createShortcodeWithRenderer();
        $shortcode->setHttpClient($client);

        $output = $shortcode->handle([
            'zip' => '33409',
            'country' => 'us',
            'scale' => 'C',
        ]);

        $this->assertStringContainsString('Location: West Palm Beach, FL', $output);
        $this->assertStringContainsString('Temp: 20', $output);
    }

    public function testWeatherShortcodeReturnsErrorWhenNoLocation(): void
    {
        $shortcode = $this->createShortcodeWithRenderer();
        $output = $shortcode->handle(['scale' => 'C']);

        $this->assertStringContainsString('Weather shortcode requires lat/long', $output);
    }

    private function createShortcodeWithRenderer(): WeatherShortcode
    {
        $logger = $this->container->get('logger');
        $renderer = new TemplateRenderer(new TemplateVariableBuilder(), $logger, null);

        $shortcode = new WeatherShortcode();
        $shortcode->setTemplateRenderer($renderer);
        $shortcode->setContainer($this->container);

        return $shortcode;
    }

    private function clearWeatherCache(): void
    {
        $pattern = sys_get_temp_dir() . '/staticforge_weather_*';
        foreach (glob($pattern) ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
