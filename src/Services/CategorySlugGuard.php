<?php

declare(strict_types=1);

namespace EICC\StaticForge\Services;

/**
 * A category slug becomes a directory name under the output dir. An empty slug
 * writes into the site root, and a dot-only slug ("." resolves inside the output
 * dir, so PathGuard does not stop it) targets the root or its parent: either
 * can overwrite the home page.
 */
final class CategorySlugGuard
{
    public static function isUnsafe(string $slug): bool
    {
        return $slug === '' || trim($slug, '.') === '';
    }
}
