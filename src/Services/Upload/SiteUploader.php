<?php

declare(strict_types=1);

namespace EICC\StaticForge\Services\Upload;

use EICC\StaticForge\Core\Events\UploadCheckFileEvent;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\FileProcessor;
use EICC\Utils\Log;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;

class SiteUploader
{
    private const MANIFEST_FILENAME = 'staticforge-manifest.json';
    public const EVENT_UPLOAD_CHECK_FILE = 'UPLOAD_CHECK_FILE';

    private SftpClientInterface $client;
    private Log $logger;
    private UploadCheckService $checkService;
    private EventManager $eventManager;

    private int $uploadedCount = 0;
    private int $errorCount = 0;
    private int $deleteFailureCount = 0;
    /** @var array<int, string> */
    private array $errors = [];

    /**
     * @var array<string, ?string> Path => Hash
     */
    private array $newManifest = [];

    /** null, 'non_interactive' or 'declined' when the delete guard stopped remote deletions */
    private ?string $deleteGuardAbort = null;

    public function __construct(
        SftpClientInterface $client,
        Log $logger,
        UploadCheckService $checkService,
        EventManager $eventManager
    ) {
        $this->client = $client;
        $this->logger = $logger;
        $this->checkService = $checkService;
        $this->eventManager = $eventManager;
    }

    /**
     * Upload files from input directory to remote path
     *
     * @param string $inputDir
     * @param string $remotePath
     * @param bool $isDryRun
     * @param OutputInterface $output
     * @return int Error count
     */
    public function upload(
        string $inputDir,
        string $remotePath,
        bool $isDryRun,
        OutputInterface $output,
        ?UploadOptions $options = null
    ): int {
        $options ??= new UploadOptions();
        $this->deleteGuardAbort = null;
        $this->uploadedCount = 0;
        $this->errorCount = 0;
        $this->deleteFailureCount = 0;
        $this->errors = [];
        $this->newManifest = [];

        // Get files to upload
        $files = $this->getFilesToUpload($inputDir);

        if (empty($files)) {
            $output->writeln('<comment>No files to upload</comment>');
            return 0;
        }

        // Initialize manifest
        $normalizedInputDir = rtrim($inputDir, '/\\');

        // Load existing manifest from remote
        $remoteManifest = $this->loadRemoteManifest($remotePath, $output);

        $output->writeln(sprintf('<info>Processing %d files...</info>', count($files)));

        // Process files
        $failedPaths = [];
        foreach ($files as $localPath) {
            $relativePath = substr($localPath, strlen($normalizedInputDir) + 1);
            $targetPath = $remotePath . '/' . $relativePath;

            // Calculate hash (normalized for text files)
            $currentHash = $this->checkService->calculateHash($localPath);
            $remoteHash = $remoteManifest[$relativePath] ?? null;

            // Determine if upload is needed
            // If remoteHash is null (new file or legacy manifest), we upload.
            // If hashes differ, we upload.
            $shouldUpload = ($remoteHash === null) || ($currentHash !== $remoteHash);

            // Fire event to allow plugins to intervene (e.g. an external asset-offload package)
            $event = new UploadCheckFileEvent(
                name: self::EVENT_UPLOAD_CHECK_FILE,
                path: $relativePath,
                localPath: $localPath,
                targetPath: $targetPath,
                currentHash: $currentHash,
                remoteHash: $remoteHash,
                shouldUpload: $shouldUpload,
            );
            $this->eventManager->fire(self::EVENT_UPLOAD_CHECK_FILE, $event);

            // If a plugin handled the upload itself, just record the hash
            if ($event->handled) {
                $this->newManifest[$relativePath] = $currentHash;
                continue;
            }

            // If plugin says skip, we obey
            if ($event->skipUpload) {
                $this->newManifest[$relativePath] = $remoteHash ?? $currentHash;
                if ($output->isVerbose()) {
                    $output->writeln(sprintf('  Skipped by plugin: %s', $this->display($relativePath)));
                }
                continue;
            }

            // Check if we should upload
            if ($event->shouldUpload) {
                if ($isDryRun) {
                    $output->writeln(sprintf('  [DRY RUN] Would upload: %s', $this->display($relativePath)));
                    // In dry run, we assume success for manifest generation check
                    $this->newManifest[$relativePath] = $currentHash;
                } else {
                    if ($this->client->uploadFile($localPath, $targetPath)) {
                        $this->uploadedCount++;
                        $this->newManifest[$relativePath] = $currentHash;
                        if ($output->isVerbose()) {
                             $output->writeln(sprintf('  Uploaded: %s', $this->display($relativePath)));
                        }
                    } else {
                        $this->errorCount++;
                        // Record error but don't stop everything?
                        $errorMsg = sprintf('Failed to upload: %s', $this->display($relativePath));
                        $this->errors[] = $errorMsg;
                        $failedPaths[] = $relativePath;
                        $output->writeln(sprintf('  <error>%s</error>', $errorMsg));
                        // Do not addToManifest if failed, so it attempts next time
                    }
                }
            } else {
                // File unchanged
                $this->newManifest[$relativePath] = $currentHash;
                if ($output->isVerbose()) {
                    $output->writeln(sprintf('  Skipping (unchanged): %s', $this->display($relativePath)));
                }
            }
        }

        // Order is files -> deletes -> manifest, and deletes only after a fully clean upload: a
        // changed file whose re-upload failed is missing from the new manifest, so cleanup would
        // otherwise delete the live remote copy of it.
        $carriedOver = [];
        $uploadFailed = $this->errorCount > 0;
        if (!$uploadFailed) {
            $carriedOver = $this->processManifestCleanup(
                $remotePath,
                $remoteManifest,
                $this->newManifest,
                $output,
                $isDryRun,
                $failedPaths,
                $options
            );
        } else {
            $output->writeln(sprintf(
                '<comment>%d upload error(s); skipping remote deletions.</comment>',
                $this->errorCount
            ));
        }

        // Update manifest
        if (!$isDryRun && !$uploadFailed) {
            // Stale entries that were not deleted stay recorded so a later run can still clean them up
            $this->updateRemoteManifest($remotePath, $this->newManifest + $carriedOver, $output);
            $this->syncRemoteHtaccess($remotePath, $options->errorDocumentPath, $output);
        }

        $output->writeln('');
        if ($this->deleteGuardAbort === 'non_interactive' && $this->errorCount === 0) {
            $output->writeln(sprintf(
                '<info>Upload complete: %d files uploaded, deletions held back by the delete guard</info>',
                $this->uploadedCount
            ));
        } else {
            $output->writeln(sprintf(
                '<info>Upload complete: %d files uploaded, %d errors</info>',
                $this->uploadedCount,
                $this->errorCount
            ));
        }

        if ($this->errorCount > 0) {
            $output->writeln('<error>Errors occurred during upload:</error>');
            foreach ($this->errors as $error) {
                $output->writeln(sprintf('  - %s', $error));
            }
        }

        return $this->errorCount;
    }

    /**
     * 'non_interactive' or 'declined' when the delete guard stopped remote deletions in the last run.
     */
    public function getDeleteGuardAbort(): ?string
    {
        return $this->deleteGuardAbort;
    }

    public function getDeleteFailureCount(): int
    {
        return $this->deleteFailureCount;
    }

    private function display(string $path): string
    {
        $clean = preg_replace('/[\x00-\x1f\x7f]/', '?', $path) ?? '';

        return OutputFormatter::escape($clean);
    }

    /**
     * @return array<string, ?string>
     */
    private function loadRemoteManifest(string $remotePath, OutputInterface $output): array
    {
        $manifestPath = $remotePath . '/' . self::MANIFEST_FILENAME;

        // Suppress simple errors if file doesn't exist
        $content = $this->client->readFile($manifestPath);

        if ($content === null) {
            if ($output->isVerbose()) {
                $output->writeln('<comment>No existing manifest found (or read failed).</comment>');
            }
            return [];
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            $output->writeln('<error>Invalid manifest format.</error>');
            return [];
        }

        // Handle migration from List (old format) to Map (new format)
        if (array_is_list($data)) {
            if ($output->isVerbose()) {
                 $output->writeln('<info>Upgrading manifest from legacy list format.</info>');
            }
            // Convert list [path1, path2] to map [path1 => null, path2 => null]
            // This forces re-upload/check but ensures structure is correct
            $data = array_fill_keys(array_filter($data, 'is_string'), null);
        }

        // Manifest paths become remote delete targets during cleanup, and the file
        // lives on the server, so a tampered entry must not reach outside $remotePath.
        $manifest = [];
        foreach ($data as $path => $hash) {
            $path = (string) $path;
            if (!$this->isSafeRelativePath($path)) {
                $output->writeln(sprintf('<error>Ignoring unsafe manifest entry: %s</error>', $this->display($path)));
                continue;
            }
            $manifest[$path] = is_string($hash) ? $hash : null;
        }

        return $manifest;
    }

    private function isSafeRelativePath(string $path): bool
    {
        if ($path === '' || preg_match('/[\x00-\x1f\x7f]/', $path) === 1 || preg_match('#^([/\\\\]|[a-zA-Z]:)#', $path) === 1) {
            return false;
        }

        foreach (preg_split('#[/\\\\]#', $path) ?: [] as $segment) {
            if ($segment === '..' || $segment === '.' || $segment === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, ?string> $oldManifest
     * @param array<string, ?string> $newManifest
     * @param array<int, string> $keepPaths Never deleted, whatever the manifests say
     * @return array<string, ?string> Stale entries (old hashes) that were NOT deleted and must stay recorded
     */
    private function processManifestCleanup(
        string $remotePath,
        array $oldManifest,
        array $newManifest,
        OutputInterface $output,
        bool $isDryRun,
        array $keepPaths,
        UploadOptions $options
    ): array {
        // Files in old manifest that are NOT in new manifest (i.e. deleted locally)
        // Server-side files this class manages itself are never stale
        $filesToDelete = array_values(array_diff(
            array_keys($oldManifest),
            array_keys($newManifest),
            [self::MANIFEST_FILENAME, '.htaccess'],
            $keepPaths
        ));

        $count = count($filesToDelete);
        if ($count === 0) {
            return [];
        }

        $carryOver = [];
        foreach ($filesToDelete as $file) {
            $carryOver[$file] = $oldManifest[$file];
        }

        if ($options->noDelete) {
            $output->writeln(sprintf('<comment>Skipping %d remote deletions (--no-delete)</comment>', $count));
            return $carryOver;
        }

        $limit = $options->maxDelete ?? max(10, (int) ceil(count($oldManifest) * 0.25));
        $tripped = $count > $limit && !$options->forceDelete;

        if ($isDryRun) {
            foreach ($filesToDelete as $file) {
                $output->writeln(sprintf('  [DRY RUN] Would delete: %s', $this->display($file)));
            }
            $output->writeln(sprintf(
                '<info>[DRY RUN] %d stale files would be deleted (limit %d).</info>',
                $count,
                $limit
            ));
            if ($tripped) {
                $output->writeln(
                    '<comment>[DRY RUN] The delete guard would trip; a real run would delete nothing '
                    . 'unless --force-delete is given (or upload.max_delete raised).</comment>'
                );
            }
            return [];
        }

        if ($tripped) {
            $output->writeln(sprintf(
                '<comment>Delete guard: %d stale files exceed the limit of %d.</comment>',
                $count,
                $limit
            ));

            if ($options->confirmDelete === null) {
                $this->deleteGuardAbort = 'non_interactive';
                $output->writeln(sprintf(
                    '<error>Aborting remote deletions: %d files would be deleted (limit %d). Re-run with '
                    . '--force-delete, or raise upload.max_delete in siteconfig.yaml. '
                    . 'Uploaded files and the manifest were kept; the stale entries stay recorded.</error>',
                    $count,
                    $limit
                ));
                return $carryOver;
            }

            if (!($options->confirmDelete)($count)) {
                $this->deleteGuardAbort = 'declined';
                $output->writeln(sprintf(
                    '<comment>Remote deletions declined; %d stale entries stay recorded in the manifest.</comment>',
                    $count
                ));
                return $carryOver;
            }
        }

        $output->writeln(sprintf('<info>Cleaning up %d stale files...</info>', $count));

        $failedDeletes = [];
        foreach ($filesToDelete as $file) {
            $fullPath = $remotePath . '/' . $file;
            if ($this->client->deleteFile($fullPath)) {
                if ($output->isVerbose()) {
                    $output->writeln(sprintf('  Deleted: %s', $this->display($file)));
                }
            } else {
                $this->deleteFailureCount++;
                $this->errorCount++;
                $errorMsg = sprintf('Failed to delete: %s', $this->display($file));
                $this->errors[] = $errorMsg;
                $failedDeletes[$file] = $oldManifest[$file];
                $output->writeln(sprintf('  <error>%s</error>', $errorMsg));
            }
        }

        return $failedDeletes;
    }

    /**
     * @param array<string, ?string> $manifestData
     */
    private function updateRemoteManifest(string $remotePath, array $manifestData, OutputInterface $output): void
    {
        $manifestPath = $remotePath . '/' . self::MANIFEST_FILENAME;
        $content = json_encode($manifestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($content === false) {
            $output->writeln('<error>Failed to encode manifest data to JSON.</error>');
            return;
        }

        if ($this->client->putContent($manifestPath, $content)) {
            if ($output->isVerbose()) {
                $output->writeln('<info>Manifest updated.</info>');
            }
        } else {
            $output->writeln('<error>Failed to update manifest file.</error>');
        }
    }

    /**
     * Site-absolute path of the 404 page for a site served from $siteUrl, or null when the URL has no usable path.
     */
    public static function errorDocumentPathForUrl(string $siteUrl): ?string
    {
        $path = parse_url($siteUrl, PHP_URL_PATH);
        if ($path === false) {
            return null;
        }

        $candidate = rtrim((string) $path, '/') . '/404.html';

        return self::isSafeErrorDocumentPath($candidate) ? $candidate : null;
    }

    private static function isSafeErrorDocumentPath(string $path): bool
    {
        return preg_match('#^(?:/[A-Za-z0-9._~-]+)*/404\.html\z#', $path) === 1 && !str_contains($path, '..');
    }

    /**
     * Adds the lines StaticForge needs to the server's .htaccess without touching anything else in it:
     * protection for the manifest, and an ErrorDocument 404 line when the site has a 404 page and the
     * file has no ErrorDocument 404 line of its own. A line inside an <IfModule> or similar block also counts,
     * so an existing setup is never overridden.
     */
    private function syncRemoteHtaccess(string $remotePath, ?string $errorDocumentPath, OutputInterface $output): void
    {
        $htaccessPath = $remotePath . '/.htaccess';
        $exists = $this->client->fileExists($htaccessPath);
        $content = '';

        // Read first to prevent accidental overwrites
        if ($exists) {
            $content = $this->client->readFile($htaccessPath);

            if ($content === null) {
                $output->writeln(
                    '<error>Warning: .htaccess exists but cannot be read. Skipping security update.</error>'
                );
                return;
            }
        }

        $additions = '';
        $notes = [];

        if (strpos($content, self::MANIFEST_FILENAME) === false) {
            $additions .= "\n<Files \"" . self::MANIFEST_FILENAME . "\">\n    Require all denied\n</Files>\n";
            $notes[] = $exists
                ? 'Securing manifest in existing .htaccess...'
                : 'Creating .htaccess to secure manifest...';
        }

        if ($errorDocumentPath !== null && !self::isSafeErrorDocumentPath($errorDocumentPath)) {
            $output->writeln(
                '<comment>Not adding an ErrorDocument line: the 404 page path derived from the upload URL '
                . 'contains characters that are not allowed.</comment>'
            );
        } elseif (
            $errorDocumentPath !== null
            && preg_match('/^[ \t]*ErrorDocument[ \t]+404\b/mi', $content) !== 1
        ) {
            $additions .= "\n# Custom 404 page (added by StaticForge)\nErrorDocument 404 {$errorDocumentPath}\n";
            $output->writeln(sprintf(
                '<info>Adding "ErrorDocument 404 %s" to the server .htaccess.</info>',
                $errorDocumentPath
            ));
        }

        if ($additions === '') {
            return;
        }

        if ($output->isVerbose()) {
            foreach ($notes as $note) {
                $output->writeln('<info>' . $note . '</info>');
            }
        }

        $body = $exists ? $content . $additions : ltrim($additions);
        if (!$this->client->putContent($htaccessPath, $body)) {
            $output->writeln('<error>Failed to ' . ($exists ? 'update' : 'create') . ' .htaccess</error>');
        }
    }

    /**
     * Get recursive list of files to upload
     *
     * @param string $directory
     * @return array<int, string>
     */
    public function getFilesToUpload(string $directory): array
    {
        $files = [];

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            $fingerprintFile = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR
                . FileProcessor::BUILD_FINGERPRINT_FILE;
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getPathname() !== $fingerprintFile) {
                    $files[] = $file->getPathname();
                }
            }
        } catch (\Exception $e) {
            $this->logger->log('ERROR', 'Failed to scan directory', [
                'directory' => $directory,
                'error' => $e->getMessage()
            ]);
        }

        return $files;
    }
}
