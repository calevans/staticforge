<?php

declare(strict_types=1);

namespace EICC\StaticForge\Services\Upload;

use EICC\Utils\Log;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Output\OutputInterface;

class SiteUploader
{
    private const MANIFEST_FILENAME = 'staticforge-manifest.json';

    private SftpClient $client;
    private Log $logger;
    private int $uploadedCount = 0;
    private int $errorCount = 0;
    /** @var array<int, string> */
    private array $errors = [];

    public function __construct(SftpClient $client, Log $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
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

        // Get files to upload
        $files = $this->getFilesToUpload($inputDir);

        if (empty($files)) {
            $output->writeln('<comment>No files to upload</comment>');
            return 0;
        }

        // Calculate relative paths for manifest logic
        $localManifest = [];
        $normalizedInputDir = rtrim($inputDir, '/\\');
        
        foreach ($files as $file) {
            $localManifest[] = substr($file, strlen($normalizedInputDir) + 1);
        }

        if ($isDryRun) {
            $output->writeln(sprintf('<info>Found %d files to upload:</info>', count($files)));
            foreach ($localManifest as $relativePath) {
                $targetPath = $remotePath . '/' . $relativePath;
                $output->writeln(sprintf('  [DRY RUN] Would upload: %s -> %s', $relativePath, $targetPath));
            }
            $this->processManifestCleanup($remotePath, $localManifest, $output, true);
            return 0;
        }

        // Cleanup stale files based on manifest
        $this->processManifestCleanup($remotePath, $localManifest, $output, false);

        $output->writeln(sprintf('<info>Uploading %d files...</info>', count($files)));

        // Upload files
        foreach ($files as $localPath) {
            $relativePath = substr($localPath, strlen($normalizedInputDir) + 1);
            $targetPath = $remotePath . '/' . $relativePath;

            if ($this->client->uploadFile($localPath, $targetPath)) {
                $this->uploadedCount++;
                if ($output->isVerbose()) {
                    $output->writeln(sprintf('  Uploaded: %s', $relativePath));
                }
            } else {
                $this->errorCount++;
                $errorMsg = sprintf('Failed to upload: %s', $relativePath);
                $this->errors[] = $errorMsg;
                $output->writeln(sprintf('  <error>%s</error>', $errorMsg));
            }
        }

        // Update manifest
        $this->updateRemoteManifest($remotePath, $localManifest, $output);

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

    private function processManifestCleanup(string $remotePath, array $localFiles, OutputInterface $output, bool $isDryRun): void
    {
        $manifestPath = $remotePath . '/' . self::MANIFEST_FILENAME;
        $content = $this->client->readFile($manifestPath);

        if ($content === null) {
            if ($output->isVerbose()) {
                $output->writeln('<comment>No existing manifest found. Skipping cleanup.</comment>');
            }
            return;
        }

        $remoteFiles = json_decode($content, true);
        if (!is_array($remoteFiles)) {
            $output->writeln('<error>Invalid manifest format. Skipping cleanup.</error>');
            return;
        }

        $filesToDelete = array_diff($remoteFiles, $localFiles);
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

    private function updateRemoteManifest(string $remotePath, array $localFiles, OutputInterface $output): void
    {
        $manifestPath = $remotePath . '/' . self::MANIFEST_FILENAME;
        $content = json_encode($localFiles, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($this->client->putContent($manifestPath, $content)) {
            if ($output->isVerbose()) {
                $output->writeln('<info>Manifest updated.</info>');
            }
        } else {
            $output->writeln('<error>Failed to update manifest file.</error>');
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
