<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\DevServer\Services;

final class HostPolicy
{
    public static function isLoopback(string $host): bool
    {
        return in_array(strtolower($host), ['127.0.0.1', 'localhost', '::1', '[::1]'], true);
    }

    public static function decide(string $host, bool $allowRemote, bool $underLando): HostDecision
    {
        if (self::isLoopback($host)) {
            return HostDecision::Run;
        }
        if ($allowRemote) {
            return HostDecision::WarnRemote;
        }

        return $underLando ? HostDecision::WarnLando : HostDecision::Refuse;
    }

    /**
     * Host header values (port stripped, lowercase) accepted by the state endpoint.
     *
     * @return list<string>
     */
    public static function allowedHosts(string $boundHost, bool $underLando, ?string $landoInfo): array
    {
        $hosts = ['localhost', '127.0.0.1', '[::1]', self::normalize($boundHost)];

        if ($underLando && $landoInfo !== null && $landoInfo !== '') {
            $info = json_decode($landoInfo, true);
            foreach (is_array($info) ? $info : [] as $service) {
                $urls = is_array($service) && is_array($service['urls'] ?? null) ? $service['urls'] : [];
                foreach ($urls as $url) {
                    $host = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;
                    if (is_string($host) && $host !== '') {
                        $hosts[] = strtolower($host);
                    }
                }
            }
        }

        return array_values(array_unique($hosts));
    }

    private static function normalize(string $host): string
    {
        $host = strtolower($host);

        return str_contains($host, ':') && !str_starts_with($host, '[') ? '[' . $host . ']' : $host;
    }
}
