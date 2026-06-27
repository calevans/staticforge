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
        $zipResponse = new MockResponse($this->jsonBody([
            'places' => [[
                'latitude' => '26.7',
                'longitude' => '-80.1',
                'place name' => 'West Palm Beach',
                'state abbreviation' => 'FL',
            ]],
        ]));

        $weatherResponse = new MockResponse($this->jsonBody([
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

    public function testWeatherShortcodeRendersFromLatLong(): void
    {
        $weatherResponse = new MockResponse($this->jsonBody([
            'current_weather' => [
                'temperature' => 15,
                'windspeed' => 10,
                'weathercode' => 0,
            ],
        ]));

        $client = new MockHttpClient([$weatherResponse]);

        $shortcode = $this->createShortcodeWithRenderer();
        $shortcode->setHttpClient($client);

        $output = $shortcode->handle([
            'lat' => '40.7',
            'long' => '-74.0',
            'scale' => 'C',
        ]);

        $this->assertStringContainsString('Location: 40.7, -74.0', $output);
        $this->assertStringContainsString('Temp: 15', $output);
        $this->assertStringContainsString('Condition: Clear sky', $output);
    }

    public function testWeatherShortcodeConvertsToFahrenheit(): void
    {
        $weatherResponse = new MockResponse($this->jsonBody([
            'current_weather' => [
                'temperature' => 0,
                'windspeed' => 1,
                'weathercode' => 0,
            ],
        ]));

        $client = new MockHttpClient([$weatherResponse]);

        $shortcode = $this->createShortcodeWithRenderer();
        $shortcode->setHttpClient($client);

        $output = $shortcode->handle([
            'lat' => '40.7',
            'long' => '-74.0',
            'scale' => 'F',
        ]);

        $this->assertStringContainsString('Temp: 32 °F', $output);
    }

    public function testWeatherShortcodeFallsBackToDefaultScaleWhenInvalid(): void
    {
        $weatherResponse = new MockResponse($this->jsonBody([
            'current_weather' => [
                'temperature' => 10,
                'windspeed' => 1,
                'weathercode' => 0,
            ],
        ]));

        $client = new MockHttpClient([$weatherResponse]);

        $shortcode = $this->createShortcodeWithRenderer();
        $shortcode->setHttpClient($client);

        $output = $shortcode->handle([
            'lat' => '40.7',
            'long' => '-74.0',
            'scale' => 'kelvin',
        ]);

        $this->assertStringContainsString('°C', $output);
    }

    public function testWeatherShortcodeReturnsErrorWhenWeatherApiFails(): void
    {
        $weatherResponse = new MockResponse('', ['http_code' => 500]);
        $client = new MockHttpClient([$weatherResponse]);

        $shortcode = $this->createShortcodeWithRenderer();
        $shortcode->setHttpClient($client);

        $output = $shortcode->handle([
            'lat' => '40.7',
            'long' => '-74.0',
        ]);

        $this->assertSame('<!-- Weather data unavailable -->', $output);
    }

    public function testWeatherShortcodeReturnsNoLocationCommentWhenZipLookupFails(): void
    {
        $zipResponse = new MockResponse('', ['http_code' => 404]);
        $client = new MockHttpClient([$zipResponse]);

        $shortcode = $this->createShortcodeWithRenderer();
        $shortcode->setHttpClient($client);

        $output = $shortcode->handle([
            'zip' => '00000',
            'country' => 'us',
        ]);

        $this->assertSame('<!-- Weather shortcode requires lat/long or valid zip -->', $output);
    }

    public function testWeatherShortcodeHandlesMalformedJsonGracefully(): void
    {
        $weatherResponse = new MockResponse('not valid json{{{');
        $client = new MockHttpClient([$weatherResponse]);

        $shortcode = $this->createShortcodeWithRenderer();
        $shortcode->setHttpClient($client);

        $output = $shortcode->handle([
            'lat' => '40.7',
            'long' => '-74.0',
        ]);

        $this->assertSame('<!-- Weather data unavailable -->', $output);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function jsonBody(array $data): string
    {
        $encoded = json_encode($data);
        $this->assertNotFalse($encoded, 'Failed to encode mock JSON response body');
        return $encoded;
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
