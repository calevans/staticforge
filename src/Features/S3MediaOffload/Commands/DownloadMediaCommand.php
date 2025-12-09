<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\S3MediaOffload\Commands;

use EICC\StaticForge\Features\S3MediaOffload\Services\S3Service;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class DownloadMediaCommand extends Command
{
    protected static $defaultName = 'media:download';
    private S3Service $s3Service;
    private string $contentPath;

    public function __construct(S3Service $s3Service, string $contentPath)
    {
        parent::__construct();
        $this->s3Service = $s3Service;
        $this->contentPath = rtrim($contentPath, '/');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Download media files from S3 and update content URLs')
            ->addArgument('directory', InputArgument::OPTIONAL, 'Directory to download to (relative to content root)', 'assets/media');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $directory = $input->getArgument('directory');
        $targetPath = $this->contentPath . '/' . $directory;

        $output->writeln("Downloading media from S3 to $targetPath...");

        // 1. List all files in S3
        // Assuming we want to download everything or just things matching the directory structure?
        // The user said "download should also change any urls to point to content/assetts/media"
        // This implies we are downloading *to* content/assets/media.
        // If the S3 bucket has a structure like `assets/images/foo.jpg`, we should probably respect that or flatten it?
        // Let's assume the S3 bucket mirrors the public/assets structure.

        // For now, let's list all files in the bucket.
        $files = $this->s3Service->listFiles();

        if (empty($files)) {
            $output->writeln("<info>No files found in S3 bucket.</info>");
            return Command::SUCCESS;
        }

        foreach ($files as $key) {
            // Determine local path
            // If key is "assets/images/foo.jpg" and we download to "content/assets/media",
            // do we keep the structure?
            // Let's assume we download to $targetPath . '/' . basename($key) to flatten?
            // Or keep structure?
            // If we are changing URLs to point to content/assets/media, it suggests a flat or specific structure.
            // Let's preserve the key structure relative to the target path.

            $localFilePath = $targetPath . '/' . $key;
            $output->write("Downloading $key... ");

            if ($this->s3Service->downloadFile($key, $localFilePath)) {
                $output->writeln("<info>Done</info>");
            } else {
                $output->writeln("<error>Failed</error>");
            }
        }

        // 2. Update URLs in content files
        $output->writeln("Updating URLs in content files...");
        $this->updateContentUrls($output, $directory);

        $output->writeln('Download and update complete.');
        return Command::SUCCESS;
    }

    private function updateContentUrls(OutputInterface $output, string $mediaDirectory): void
    {
        // Scan all markdown files in content directory
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->contentPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $s3UrlPattern = '/https?:\/\/[^\s"\']+\.s3\.[^\s"\']+\.amazonaws\.com\/([^\s"\']+)/';
        // Also handle custom endpoints if possible, but regex is hard without knowing the endpoint.
        // Let's assume standard S3 URLs for now or try to match based on the filename if we knew it.
        // But we don't know which files correspond to which S3 URLs easily without a map.
        // However, the user said "change ANY urls".

        // A safer approach might be to look for URLs that match the files we just downloaded.
        // But that's expensive.

        // Let's try to match generic S3 URLs.

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'md') {
                $content = file_get_contents($file->getPathname());
                $originalContent = $content;

                // Replace S3 URLs with local paths
                // Replacement: /assets/media/$1
                // Note: $mediaDirectory is relative to content root.
                // In the built site, it will be relative to root.
                // If we download to content/assets/media, the public URL is /assets/media/...

                // We need to handle the leading slash correctly.
                $replacement = '/' . $mediaDirectory . '/$1';

                $content = preg_replace($s3UrlPattern, $replacement, $content);

                if ($content !== $originalContent) {
                    file_put_contents($file->getPathname(), $content);
                    $output->writeln("Updated URLs in " . $file->getFilename());
                }
            }
        }
    }
}
