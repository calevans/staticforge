<?php

declare(strict_types=1);

namespace EICC\StaticForge\Core\Events;

/**
 * Fired at PRE_RENDER, RENDER, POST_RENDER, and MARKDOWN_CONVERTED. Mutable —
 * each listener in the chain reads and writes these properties in place,
 * mirroring how the old array-based $parameters accumulated changes across
 * listeners. $extra holds feature-specific keys that don't have a first-class
 * property (pagination bypass flags, computed TOC/reading-time data, etc.) —
 * same "known fields + extra" shape as Frontmatter, for the same reason: this
 * bag is genuinely open-ended and owned by whichever Feature reads/writes it.
 */
class RenderEvent extends Event
{
    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $extra
     */
    public function __construct(
        string $name,
        public string $filePath,
        public string $fileUrl,
        public array $metadata,
        public ?string $renderedContent = null,
        public ?string $outputPath = null,
        public bool $skipFile = false,
        public bool $cacheHit = false,
        public array $extra = [],
    ) {
        parent::__construct($name);
    }
}
