<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\Deployment\Commands;

use EICC\StaticForge\Services\Upload\SftpClient;
use EICC\StaticForge\Services\Upload\SftpConfigLoader;
use EICC\StaticForge\Services\Upload\SiteUploader;
use EICC\StaticForge\Core\Application;
use EICC\Utils\Container;
use EICC\Utils\Log;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UploadSiteCommand extends Command
{
    protected Container $container;
    protected Log $logger;
    protected SftpConfigLoader $configLoader;
    protected SftpClient $sftpClient;
    protected SiteUploader $siteUploader;

    public function __construct(Container $container)
    {
        parent::__construct();
        $this->container = $container;
        $this->logger = $container->get('logger');

        // Initialize helpers
        $this->configLoader = new SftpConfigLoader();
        $this->sftpClient = new SftpClient($this->logger);
        $this->siteUploader = new SiteUploader($this->sftpClient, $this->logger);
    }

    protected function configure(): void
    {
        $this
        ->setName('site:upload')
        ->setDescription('Upload generated static site to remote server via SFTP')
        ->addOption(
            'input',
            null,
            InputOption::VALUE_REQUIRED,
            'Override output directory to upload (default from OUTPUT_DIR in .env)'
        )
        ->addOption(
            'test',
            null,
            InputOption::VALUE_NONE,
            'Perform a dry run (connect, verify, list files) without uploading'
        )
        ->addOption(
            'url',
            null,
            InputOption::VALUE_REQUIRED,
            'Override site base URL and re-render site before uploading'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tempDir = null;

        try {
            $isTest = $input->getOption('test');
            $urlOverride = $input->getOption('url');

            // Check for UPLOAD_URL in environment if not provided via CLI
            if (!$urlOverride) {
                $urlOverride = $_ENV['UPLOAD_URL'] ?? null;
            }

            if (!$urlOverride) {
                $output->writeln(
                    '<error>Upload URL is required. Please set UPLOAD_URL in .env or use --url option.</error>'
                );
                return Command::FAILURE;
            }

            // Load and validate configuration
            $config = $this->configLoader->load($input, $this->container);

            // Handle URL override and re-rendering
            if (!empty($urlOverride)) {
                // Ensure URL ends with a trailing slash
                $urlOverride = rtrim($urlOverride, '/') . '/';

                $output->writeln("<info>URL override detected: {$urlOverride}</info>");
                $output->writeln('<info>Re-rendering site for production...</info>');

                // Create temporary directory
                $baseTmpDir = $this->container->getVariable('TMP_DIR') ?? sys_get_temp_dir();
                $tempDir = $baseTmpDir . '/staticforge_build_' . uniqid();

                if (!mkdir($tempDir, 0755, true)) {
                    throw new \RuntimeException("Failed to create temporary directory: {$tempDir}");
                }

                $output->writeln("<comment>Building to temporary directory: {$tempDir}</comment>");

                // Update container with override values
                $this->container->updateVariable('OUTPUT_DIR', $tempDir);
                $this->container->updateVariable('SITE_BASE_URL', $urlOverride);

                // Run the render process
                $app = new Application($this->container);
                if (!$app->generate()) {
                    throw new \RuntimeException('Site generation failed');
                }

                // Update config to point to the temp directory for upload
                $config['input_dir'] = $tempDir;
            }

            if ($isTest) {
                $output->writeln('<info>Running in TEST mode (Dry Run)</info>');
            } else {
                $output->writeln('<info>Starting SFTP upload...</info>');
            }

            // Establish SFTP connection
            if (!$this->sftpClient->connect($config)) {
                $output->writeln('<error>Failed to connect to SFTP server</error>');
                return Command::FAILURE;
            }

            $output->writeln(sprintf(
                '<info>Connected to %s as %s</info>',
                $config['host'],
                $config['username']
            ));

            // Prepare for upload
            if (!$this->sftpClient->ensureRemoteDirectory($config['remote_path'])) {
                $output->writeln('<error>Failed to create/verify remote directory</error>');
                $this->sftpClient->disconnect();
                return Command::FAILURE;
            }

            // Perform upload
            $errorCount = $this->siteUploader->upload(
                $config['input_dir'],
                $config['remote_path'],
                (bool)$isTest,
                $output
            );

            $this->sftpClient->disconnect();

            if ($errorCount > 0) {
                return Command::FAILURE;
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
            $this->logger->log('ERROR', 'Upload failed', ['error' => $e->getMessage()]);
            $this->sftpClient->disconnect();
            return Command::FAILURE;
        } finally {
            // Clean up temporary directory if it was created
            if ($tempDir && is_dir($tempDir)) {
                $output->writeln("<comment>Cleaning up temporary directory...</comment>");
                $this->recursiveDelete($tempDir);
            }
        }
    }

    /**
     * Recursively delete a directory
     */
    protected function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }

        rmdir($dir);
    }
}
