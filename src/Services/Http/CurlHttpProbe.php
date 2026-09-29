<?php

declare(strict_types=1);

namespace EICC\StaticForge\Services\Http;

class CurlHttpProbe implements HttpProbeInterface
{
    private const TIMEOUT_SECONDS = 10;

    public function probe(string $url, bool $verifyTls = true): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['status' => 0, 'error' => 'Could not initialise cURL'];
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => $verifyTls,
            CURLOPT_SSL_VERIFYHOST => $verifyTls ? 2 : 0,
            // Abort on the first body byte: the status is all these checks need.
            CURLOPT_WRITEFUNCTION => static fn ($handle, string $data): int => 0,
        ]);

        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);

        return ['status' => $status, 'error' => $status > 0 ? '' : $error];
    }
}
