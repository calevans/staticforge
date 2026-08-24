<?php

declare(strict_types=1);

namespace EICC\StaticForge\Core;

/**
 * Strips keys from parsed YAML frontmatter that a content author must never
 * be able to set — either because they're reserved for values Core computes
 * itself (content, paths), or because they look like a credential a template
 * or feature might otherwise read out of the container (SFTP_PASSWORD-shaped
 * names). Ordinary page fields (title, tags, custom fields, ...) pass through
 * untouched; this is not a general frontmatter schema.
 */
final class Frontmatter
{
    /**
     * @var array<int, string>
     */
    private const RESERVED_KEYS = [
        'content',
        'app_root',
        'source_file',
        'OUTPUT_DIR',
        'SOURCE_DIR',
        'TEMPLATE_DIR',
        'FEATURES_DIR',
    ];

    private const CREDENTIAL_PATTERN = '/PASSWORD|SECRET|TOKEN|_KEY$/i';

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public static function stripReserved(array $metadata): array
    {
        foreach (array_keys($metadata) as $key) {
            if (!is_string($key)) {
                continue;
            }

            if (in_array($key, self::RESERVED_KEYS, true) || preg_match(self::CREDENTIAL_PATTERN, $key) === 1) {
                unset($metadata[$key]);
            }
        }

        return $metadata;
    }
}
