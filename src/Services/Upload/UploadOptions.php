<?php

declare(strict_types=1);

namespace EICC\StaticForge\Services\Upload;

/**
 * Per-run deletion behaviour for SiteUploader.
 */
final class UploadOptions
{
    /**
     * @param ?int $maxDelete Configured guard limit; null = max(10, 25% of the old manifest)
     * @param (\Closure(int): bool)|null $confirmDelete Asked when the guard trips; null = non-interactive
     */
    public function __construct(
        public readonly bool $noDelete = false,
        public readonly bool $forceDelete = false,
        public readonly ?int $maxDelete = null,
        public readonly ?\Closure $confirmDelete = null,
    ) {
    }
}
