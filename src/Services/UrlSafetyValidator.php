<?php

declare(strict_types=1);

namespace EICC\StaticForge\Services;

use InvalidArgumentException;

/**
 * SSRF guard for commands that fetch an operator-supplied or content-scraped
 * URL (audit:live, audit:links): rejects non-http(s) schemes and hosts that
 * resolve to a private, loopback, or otherwise non-public address, so a
 * crafted URL can't be used to reach internal network services.
 */
final class UrlSafetyValidator
{
    /**
     * @throws InvalidArgumentException if $url is not safe to fetch
     */
    public static function assertSafe(string $url): void
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'])) {
            throw new InvalidArgumentException("Invalid URL: {$url}");
        }

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException("URL scheme must be http or https, got '{$scheme}': {$url}");
        }

        if (!isset($parts['host'])) {
            throw new InvalidArgumentException("Invalid URL: {$url}");
        }

        $host = $parts['host'];

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $ip = $host;
        } else {
            $resolved = gethostbyname($host);
            if ($resolved === $host) {
                throw new InvalidArgumentException("Could not resolve host: {$host}");
            }
            $ip = $resolved;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw new InvalidArgumentException(
                "URL host resolves to a private, loopback, or reserved address: {$url}"
            );
        }
    }
}
