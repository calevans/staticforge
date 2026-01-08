<?php

declare(strict_types=1);

namespace EICC\StaticForge\Services\Upload;

use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Log;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Output\OutputInterface;

class SiteUploader
{
    private const MANIFEST_FILENAME = 'staticforge-manifest.json';
    public const EVENT_UPLOAD_CHECK_FILE = 'UPLOAD_CHECK_FILE';

    private SftpClient $client;
    private Log $logger;
    private UploadCheckService $checkService;
    private EventManager $eventManager;

    private int $uploadedCount = 0;
    private int $errorCount = 0;
    /** @var array<int, string> */
    private array $errors = [];

    /**
     * @var array<string, ?string> Path => Hash
     */
    private array $newManifest = [];

    public function __construct(
        SftpClient $client,
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
    public function upload(string $inputDir, string $remotePath, bool $isDryRun, OutputInterface $output): int
    {
        $this->uploadedCount = 0;
        $this->errorCount = 0;
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

            // Prepare event context
            $eventData = [
                'path' => $relativePath,
                'local_path' => $localPath,
                'target_path' => $targetPath,
                'current_hash' => $currentHash,
                'remote_hash' => $remoteHash,
                'should_upload' => $shouldUpload,
                // Plugins can set these:
                'skip_upload' => false,
                'handled' => false
            ];

            // Fire event to allow plugins (like S3) to intervene
            $eventData = $this->eventManager->fire(self::EVENT_UPLOAD_CHECK_FILE, $eventData);

            // If plugin handled the upload (e.g. S3), just record the hash
            if (!empty($eventData['handled'])) {
                $this->newManifest[$relativePath] = $currentHash;
                continue;
            }

            // If plugin says skip, we obey
            if (!empty($eventData['skip_upload'])) {
                $this->newManifest[$relativePath] = $remoteHash ?? $currentHash;
                if ($output->isVerbose()) {
                    $output->writeln(sprintf('  Skipped by plugin: %s', $relativePath));
                }
                continue;
            }

            // Check if we should upload
            if ($eventData['should_upload']) {
                if ($isDryRun) {
                    $output->writeln(sprintf('  [DRY RUN] Would upload: %s', $relativePath));
                    // In dry run, we assume success for manifest generation check
                    $this->newManifest[$relativePath] = $currentHash;
                } else {
                    if ($this->client->uploadFile($localPath, $targetPath)) {
                        $this->uploadedCount++;
                        $this->newManifest[$relativePath] = $currentHash;
                        if ($output->isVerbose()) {
                             $output->writeln(sprintf('  Uploaded: %s', $relativePath));
                        }
                    } else {
                        $this->errorCount++;
                        // Record error but don't stop everything?
                        $errorMsg = sprintf('Failed to upload: %s', $relativePath);
                        $this->errors[] = $errorMsg;
                        $output->writeln(sprintf('  <error>%s</error>', $errorMsg));
                        // Do not addToManifest if failed, so it attempts next time
                    }
                }
            } else {
                // File unchanged
                $this->newManifest[$relativePath] = $currentHash;
                if ($output->isVerbose()) {
                    $output->writeln(sprintf('  Skipping (unchanged): %s', $relativePath));
                }
            }
        }

        // Handle Cleanup (Files in old manifest but not in new manifest)
        $this->processManifestCleanup($remotePath, $remoteManifest, $this->newManifest, $output, $isDryRun);

        // Update manifest
        if (!$isDryRun && $this->errorCount === 0) {
            $this->updateRemoteManifest($remotePath, $this->newManifest, $output);
            $this->secureRemoteManifest($remotePath, $output);
        }

        $output->writeln('');
        $output->writeln(sprintf(
            '<info>Upload complete: %d files uploaded, %d errors</info>',
            $this->uploadedCount,
            $this->errorCount
        ));

        if ($this->errorCount > 0) {
            $output->writeln('<error>Errors occurred during upload:</error>');
            foreach ($this->errors as $error) {
                $output->writeln(sprintf('  - %s', $error));
            }
        }

        return $this->errorCount;
    }

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
            return array_fill_keys($data, null);
        }

        return $data;
    }

    private function processManifestCleanup(
        string $remotePath,
        array $oldManifest,
        array $newManifest,
        OutputInterface $output,
        bool $isDryRun
    ): void {
        // Files in old manifest that are NOT in new manifest (i.e. deleted locally)
        // Check keys which are paths
        $oldFiles = array_keys($oldManifest);
        $newFiles = array_keys($newManifest);

        $filesToDelete = array_diff($oldFiles, $newFiles);

        if (empty($filesToDelete)) {
            return;
        }

        $output->writeln(sprintf('<info>Cleaning up %d stale files...</info>', count($filesToDelete)));

        foreach ($filesToDelete as $file) {
            if ($isDryRun) {
                $output->writeln(sprintf('  [DRY RUN] Would delete: %s', $file));
                continue;
            }

            $fullPath = $remotePath . '/' . $file;
            if ($this->client->deleteFile($fullPath)) {
                if ($output->isVerbose()) {
                    $output->writeln(sprintf('  Deleted: %s', $file));
                }
            } else {
                $output->writeln(sprintf('  <error>Failed to delete: %s</error>', $file));
            }
        }
    }

    private function updateRemoteManifest(string $remotePath, array $manifestData, OutputInterface $output): void
    {
        $manifestPath = $remotePath . '/' . self::MANIFEST_FILENAME;
        $content = json_encode($manifestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($this->client->putContent($manifestPath, $content)) {
            if ($output->isVerbose()) {
                $output->writeln('<info>Manifest updated.</info>');
            }
        } else {
            $output->writeln('<error>Failed to update manifest file.</error>');
        }
    }

    private function secureRemoteManifest(string $remotePath, OutputInterface $output): void
    {
        $htaccessPath = $remotePath . '/.htaccess';
        $block = "\n<Files \"" . self::MANIFEST_FILENAME . "\">\n    Require all denied\n</Files>\n";

        // Check existence first to prevent accidental overwrites
        if ($this->client->fileExists($htaccessPath)) {
            $content = $this->client->readFile($htaccessPath);

            if ($content === null) {
                $output->writeln('<error>Warning: .htaccess exists but cannot be read. Skipping security update.</error>');
                return;
            }

            if (strpos($content, self::MANIFEST_FILENAME) === false) {
                if ($output->isVerbose()) {
                    $output->writeln('<info>Securing manifest in existing .htaccess...</info>');
                }
                if (!$this->client->putContent($htaccessPath, $content . $block)) {
                    $output->writeln('<error>Failed to update .htaccess</error>');
                }
            }
        } else {
            if ($output->isVerbose()) {
                $output->writeln('<info>Creating .htaccess to secure manifest...</info>');
            }
            if (!$this->client->putContent($htaccessPath, ltrim($block))) {
                $output->writeln('<error>Failed to create .htaccess</error>');
            }
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

            foreach ($iterator as $file) {
                if ($file->isFile()) {
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
