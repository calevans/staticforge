<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\FeatureTools\Models;

/**
 * Outcome of running FeatureMigrator against one Feature.php file.
 */
class MigrationResult
{
    /**
     * @param string[] $warnings Non-fatal notes (auto-mapped to extra[], etc.) — file was still migrated.
     */
    public function __construct(
        public readonly string $filePath,
        public readonly bool $alreadyMigrated,
        public readonly bool $skipped,
        public readonly ?string $skipReason,
        public readonly string $originalContent,
        public readonly string $migratedContent,
        public readonly int $listenersConverted,
        public array $warnings = [],
    ) {
    }

    public function changed(): bool
    {
        return !$this->alreadyMigrated && !$this->skipped && $this->originalContent !== $this->migratedContent;
    }
}
