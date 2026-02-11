<?php

declare(strict_types=1);

namespace EICC\StaticForge\Shortcodes;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WeatherShortcode extends BaseShortcode
{
    private const CACHE_PREFIX = 'staticforge_weather_';
    private const DEFAULT_TIMEOUT = 5;

    private ?HttpClientInterface $httpClient = null;

    public function getName(): string
    {
        return 'weather';
    }

    public function setHttpClient(HttpClientInterface $client): void
    {
        $this->httpClient = $client;
    }

    public function handle(array $attributes, string $content = ''): string
    {
        $zip = $attributes['zip'] ?? null;
        $country = $attributes['country'] ?? 'us';
        $lat = $attributes['lat'] ?? null;
        $long = $attributes['long'] ?? null;
        $scale = strtoupper($attributes['scale'] ?? 'C');

        if (!in_array($scale, ['C', 'F'])) {
            $scale = 'C';
        }

        if ($zip) {
            $coords = $this->getCoordinatesFromZip($zip, $country);
            if ($coords) {
                $lat = $coords['lat'];
                $long = $coords['lon'];
                $locationName = $coords['name'];
            }
        }

        if (!$lat || !$long) {
            return '<!-- Weather shortcode requires lat/long or valid zip -->';
        }

        $weather = $this->getWeather((string)$lat, (string)$long);

        if (!$weather) {
            return '<!-- Weather data unavailable -->';
        }

        $temperature = $weather['temperature'];
        $unit = '°C';

        if ($scale === 'F') {
            $temperature = ($temperature * 9 / 5) + 32;
            $temperature = round($temperature, 1);
            $unit = '°F';
        }

        return $this->render('shortcodes/weather.twig', [
            'location' => $locationName ?? "{$lat}, {$long}",
            'temperature' => $temperature,
            'windspeed' => $weather['windspeed'],
            'condition' => $this->getWeatherCondition($weather['weathercode']),
            'unit' => $unit
        ]);
    }

    /**
     * @return array{lat: float, lon: float, name: string}|null
     */
    private function getCoordinatesFromZip(string $zip, string $country): ?array
    {
        $cacheKey = "geo_{$country}_{$zip}";
        $data = $this->getFromCache($cacheKey);

        if (!$data) {
            $url = "https://api.zippopotam.us/{$country}/{$zip}";
            $json = $this->fetchJson($url);

            if (isset($json['places'][0])) {
                $place = $json['places'][0];
                $lat = $place['latitude'] ?? null;
                $lon = $place['longitude'] ?? null;
                $name = $place['place name'] ?? null;
                $region = $place['state abbreviation'] ?? null;

                if (is_numeric($lat) && is_numeric($lon) && is_string($name) && is_string($region)) {
                    $data = [
                        'lat' => (float)$lat,
                        'lon' => (float)$lon,
                        'name' => $name . ', ' . $region,
                    ];
                    $this->saveToCache($cacheKey, $data);
                }
            }
        }

        return $data;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getWeather(string $lat, string $long): ?array
    {
        $cacheKey = "weather_{$lat}_{$long}";
        $data = $this->getFromCache($cacheKey, 3600); // 1 hour cache

        if (!$data) {
            $url = "https://api.open-meteo.com/v1/forecast?latitude={$lat}&longitude={$long}&current_weather=true";
            $json = $this->fetchJson($url);

            if (isset($json['current_weather']) && is_array($json['current_weather'])) {
                $data = $json['current_weather'];
                $this->saveToCache($cacheKey, $data);
            }
        }

        return $data;
    }

    /**
     * @return mixed
     */
    private function getFromCache(string $key, int $ttl = 86400)
    {
        $file = sys_get_temp_dir() . '/' . self::CACHE_PREFIX . md5($key) . '.json';
        if (file_exists($file)) {
            if (time() - filemtime($file) < $ttl) {
                $content = file_get_contents($file);
                if ($content !== false) {
                    $data = json_decode($content, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return $data;
                    }
                }
            }
            unlink($file);
        }
        return null;
    }

    /**
     * @param mixed $data
     */
    private function saveToCache(string $key, $data): void
    {
        $file = sys_get_temp_dir() . '/' . self::CACHE_PREFIX . md5($key) . '.json';
        $payload = json_encode($data);
        if ($payload !== false) {
            file_put_contents($file, $payload);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchJson(string $url): ?array
    {
        try {
            $response = $this->getHttpClient()->request('GET', $url);
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                return null;
            }

            $content = $response->getContent(false);
            $data = json_decode($content, true);

            return is_array($data) ? $data : null;
        } catch (TransportExceptionInterface $e) {
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function getHttpClient(): HttpClientInterface
    {
        if ($this->httpClient !== null) {
            return $this->httpClient;
        }

        $this->httpClient = HttpClient::create([
            'timeout' => self::DEFAULT_TIMEOUT,
            'headers' => ['User-Agent' => 'StaticForge-Weather/1.0'],
        ]);

        return $this->httpClient;
    }

    private function getWeatherCondition(int $code): string
    {
        // WMO Weather interpretation codes (WW)
        // https://open-meteo.com/en/docs
        $codes = [
            0 => 'Clear sky',
            1 => 'Mainly clear', 2 => 'Partly cloudy', 3 => 'Overcast',
            45 => 'Fog', 48 => 'Depositing rime fog',
            51 => 'Drizzle: Light', 53 => 'Drizzle: Moderate', 55 => 'Drizzle: Dense',
            61 => 'Rain: Slight', 63 => 'Rain: Moderate', 65 => 'Rain: Heavy',
            71 => 'Snow: Slight', 73 => 'Snow: Moderate', 75 => 'Snow: Heavy',
            95 => 'Thunderstorm: Slight or moderate',
        ];

        return $codes[$code] ?? 'Unknown';
    }
}
