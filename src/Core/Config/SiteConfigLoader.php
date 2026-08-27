<?php

declare(strict_types=1);

namespace EICC\StaticForge\Core\Config;

use EICC\Utils\Log;
use EICC\StaticForge\Core\YamlParser;

/**
 * Loads siteconfig.yaml (base) and deep-merges every siteconfig.d/*.yaml
 * override file on top, in alphabetical filename order.
 *
 * This class has no Container dependency: it runs during bootstrap.php,
 * before the Container's logger service is registered, so it must operate
 * on primitives only and accept a nullable logger for best-effort logging.
 */
final class SiteConfigLoader
{
    /**
     * @return array<string, mixed>
     */
    public function load(string $appRoot, string $cwd, ?Log $logger = null): array
    {
        $baseConfig = $this->loadBaseConfig($appRoot, $cwd);
        $overrideDir = $this->resolveOverrideDir($appRoot, $cwd);

        if ($overrideDir === null) {
            return $baseConfig;
        }

        $overrideFiles = glob($overrideDir . '/*.yaml') ?: [];
        sort($overrideFiles, SORT_STRING);

        $mergedConfig = $baseConfig;
        foreach ($overrideFiles as $overrideFile) {
            if (!is_file($overrideFile)) {
                continue;
            }

            $overrideConfig = $this->parseFile($overrideFile);
            $mergedConfig = $this->deepMerge($mergedConfig, $overrideConfig, $logger);
        }

        return $mergedConfig;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadBaseConfig(string $appRoot, string $cwd): array
    {
        $baseConfigPaths = [
            $cwd . '/siteconfig.yaml',
            $appRoot . 'siteconfig.yaml',
        ];

        foreach ($baseConfigPaths as $baseConfigPath) {
            if (file_exists($baseConfigPath)) {
                return $this->parseFile($baseConfigPath);
            }
        }

        return [];
    }

    private function resolveOverrideDir(string $appRoot, string $cwd): ?string
    {
        $candidateDirs = [
            $cwd . '/siteconfig.d',
            $appRoot . 'siteconfig.d',
        ];

        foreach ($candidateDirs as $candidateDir) {
            if (is_dir($candidateDir)) {
                return $candidateDir;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseFile(string $path): array
    {
        try {
            $parsed = YamlParser::parseFile($path);
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Critical Error: Failed to parse siteconfig file at {$path}: " . $e->getMessage(),
                0,
                $e
            );
        }

        return is_array($parsed) ? $parsed : [];
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     * @param array<int, string> $path
     * @return array<string, mixed>
     */
    private function deepMerge(array $base, array $override, ?Log $logger, array $path = []): array
    {
        $merged = $base;

        foreach ($override as $key => $overrideValue) {
            $currentPath = [...$path, (string)$key];

            if (!array_key_exists($key, $merged)) {
                $merged[$key] = $overrideValue;
                continue;
            }

            $baseValue = $merged[$key];

            if (is_array($baseValue) && is_array($overrideValue)) {
                if (array_is_list($overrideValue)) {
                    $merged[$key] = $overrideValue;
                } else {
                    $merged[$key] = $this->deepMerge($baseValue, $overrideValue, $logger, $currentPath);
                }
                continue;
            }

            if (is_array($baseValue) !== is_array($overrideValue)) {
                $logger?->log(
                    'WARNING',
                    'siteconfig.d merge type mismatch',
                    ['key' => implode('.', $currentPath)]
                );
            }

            $merged[$key] = $overrideValue;
        }

        return $merged;
    }
}
