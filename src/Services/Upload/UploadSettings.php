<?php

declare(strict_types=1);

namespace EICC\StaticForge\Services\Upload;

/**
 * The flat `upload:` block of siteconfig.yaml. Shared by site:upload (which aborts on errors)
 * and audit:config (which reports them) so both apply one rule set.
 */
final class UploadSettings
{
    public const STRATEGY_IN_PLACE = 'in_place';

    /**
     * @param list<string> $errors
     */
    public function __construct(
        public readonly ?int $maxDelete = null,
        public readonly string $strategy = self::STRATEGY_IN_PLACE,
        public readonly array $errors = [],
    ) {
    }

    public static function fromConfig(mixed $raw): self
    {
        if ($raw === null) {
            return new self();
        }

        if (!is_array($raw)) {
            return new self(errors: ["'upload' in siteconfig.yaml must be a mapping (max_delete, strategy)."]);
        }

        $errors = [];
        $maxDelete = null;
        if (array_key_exists('max_delete', $raw) && $raw['max_delete'] !== null) {
            if (is_int($raw['max_delete']) && $raw['max_delete'] >= 0) {
                $maxDelete = $raw['max_delete'];
            } else {
                $errors[] = 'upload.max_delete must be null or a non-negative integer.';
            }
        }

        $strategy = self::STRATEGY_IN_PLACE;
        if (array_key_exists('strategy', $raw) && $raw['strategy'] !== null) {
            if ($raw['strategy'] === 'atomic') {
                $errors[] = 'upload.strategy: atomic is not available in this version.';
            } elseif ($raw['strategy'] !== self::STRATEGY_IN_PLACE) {
                $errors[] = 'upload.strategy must be in_place.';
            }
        }

        return new self($maxDelete, $strategy, $errors);
    }
}
