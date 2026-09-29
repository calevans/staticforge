<?php

declare(strict_types=1);

namespace EICC\StaticForge\Services;

final class MetadataFlags
{
    public static function isTrue(mixed $value): bool
    {
        return self::parse($value) === true;
    }

    public static function isFalse(mixed $value): bool
    {
        return self::parse($value) === false;
    }

    /**
     * True for the string "no" and for boolean false (YAML 1.1 parses bare `no` as false).
     */
    public static function robotsBlocked(mixed $value): bool
    {
        if ($value === false) {
            return true;
        }

        return is_string($value) && strtolower(trim($value)) === 'no';
    }

    private static function parse(mixed $value): ?bool
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
