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

class UploadMediaCommand extends Command
{
    protected static $defaultName = 'media:upload';
    private S3Service $s3Service;
    private string $publicPath;

    public function __construct(S3Service $s3Service, string $publicPath)
    {
        parent::__construct();
        $this->s3Service = $s3Service;
        $this->publicPath = rtrim($publicPath, '/');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Offload media files to S3')
            ->addArgument('directory', InputArgument::OPTIONAL, 'Directory to offload (relative to public root)', 'assets');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $directory = $input->getArgument('directory');
        $sourcePath = $this->publicPath . '/' . $directory;

        if (!is_dir($sourcePath)) {
            $output->writeln("<error>Directory not found: $sourcePath</error>");
            return Command::FAILURE;
        }

        $output->writeln("Offloading media from $sourcePath to S3...");

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourcePath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $filePath = $file->getPathname();
                $relativePath = substr($filePath, strlen($this->publicPath) + 1);

                // Normalize path separators for S3 keys
                $key = str_replace('\\', '/', $relativePath);

                $output->write("Uploading $key... ");
                if ($this->s3Service->uploadFile($filePath, $key)) {
                    $output->writeln("<info>Done</info>");
                } else {
                    $output->writeln("<error>Failed</error>");
                }
            }
        }

        $output->writeln('Offload complete.');
        return Command::SUCCESS;
    }
}
