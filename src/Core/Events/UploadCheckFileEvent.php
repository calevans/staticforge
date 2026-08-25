<?php

declare(strict_types=1);

namespace EICC\StaticForge\Core\Events;

/**
 * Fired by SiteUploader before uploading each file, so a Feature (e.g. an
 * external asset-offload package) can intervene: skip the upload, or claim
 * it handled the file itself. $skipUpload/$handled are the two mutable
 * fields listeners are meant to set; everything else describes the file.
 */
class UploadCheckFileEvent extends Event
{
    public function __construct(
        string $name,
        public readonly string $path,
        public readonly string $localPath,
        public readonly string $targetPath,
        public readonly string $currentHash,
        public readonly ?string $remoteHash,
        public readonly bool $shouldUpload,
        public bool $skipUpload = false,
        public bool $handled = false,
    ) {
        parent::__construct($name);
    }
}
