<?php

declare(strict_types=1);

namespace EICC\StaticForge\Shortcodes;

class WeatherShortcode extends BaseShortcode
{
    public function getName(): string
    {
        return 'weather';
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
            $url = "http://api.zippopotam.us/{$country}/{$zip}";
            $response = @file_get_contents($url);

            if ($response) {
                $json = json_decode($response, true);
                if (isset($json['places'][0])) {
                    $data = [
                        'lat' => $json['places'][0]['latitude'],
                        'long' => $json['places'][0]['longitude'],
                        'place' => $json['places'][0]['place name'] . ', ' . $json['places'][0]['state abbreviation']
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
            $response = @file_get_contents($url);

            if ($response) {
                $json = json_decode($response, true);
                if (isset($json['current_weather'])) {
                    $data = $json['current_weather'];
                    $this->saveToCache($cacheKey, $data);
                }
            }
        }

        return $data;
    }

    /**
     * @return mixed
     */
    private function getFromCache(string $key, int $ttl = 86400)
    {
        $file = sys_get_temp_dir() . '/staticforge_weather_' . md5($key);
        if (file_exists($file)) {
            if (time() - filemtime($file) < $ttl) {
                $content = file_get_contents($file);
                if ($content !== false) {
                    return unserialize($content);
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
        $file = sys_get_temp_dir() . '/staticforge_weather_' . md5($key);
        file_put_contents($file, serialize($data));
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
