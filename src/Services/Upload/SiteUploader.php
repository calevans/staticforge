<?php

declare(strict_types=1);

namespace EICC\StaticForge\Services\Upload;

use EICC\Utils\Log;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Output\OutputInterface;

class SiteUploader
{
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

        if ($isDryRun) {
            $output->writeln(sprintf('<info>Found %d files to upload:</info>', count($files)));
            foreach ($files as $localPath) {
                $relativePath = substr($localPath, strlen($inputDir) + 1);
                $targetPath = $remotePath . '/' . $relativePath;
                $output->writeln(sprintf('  [DRY RUN] Would upload: %s -> %s', $relativePath, $targetPath));
            }
            return 0;
        }

        $output->writeln(sprintf('<info>Uploading %d files...</info>', count($files)));

        // Upload files
        foreach ($files as $localPath) {
            $relativePath = substr($localPath, strlen($inputDir) + 1);
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
