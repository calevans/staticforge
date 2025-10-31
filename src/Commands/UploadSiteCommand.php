<?php

declare(strict_types=1);

namespace EICC\StaticForge\Commands;

use EICC\Utils\Container;
use EICC\Utils\Log;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;
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
  protected ?SFTP $sftp = null;
  protected int $uploadedCount = 0;
  protected int $errorCount = 0;
  /**
   * @var array<int, string>
   */
  protected array $errors = [];

  public function __construct(Container $container)
  {
    parent::__construct();
    $this->container = $container;
    $this->logger = $container->get('logger');
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
      );
  }

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    try {
      // Load and validate configuration
      $config = $this->loadConfiguration($input);

      $output->writeln('<info>Starting SFTP upload...</info>');

      // Establish SFTP connection
      if (!$this->connectSftp($config)) {
        $output->writeln('<error>Failed to connect to SFTP server</error>');
        return Command::FAILURE;
      }

      $output->writeln(sprintf(
        '<info>Connected to %s as %s</info>',
        $config['host'],
        $config['username']
      ));

      // Prepare for upload
      if (!$this->ensureRemoteDirectory($config['remote_path'])) {
        $output->writeln('<error>Failed to create/verify remote directory</error>');
        $this->disconnect();
        return Command::FAILURE;
      }

      // Get files to upload
      $files = $this->getFilesToUpload($config['input_dir']);

      if (empty($files)) {
        $output->writeln('<comment>No files to upload</comment>');
        $this->disconnect();
        return Command::SUCCESS;
      }

      $output->writeln(sprintf('<info>Uploading %d files...</info>', count($files)));

      // Upload files
      foreach ($files as $localPath) {
        $relativePath = substr($localPath, strlen($config['input_dir']) + 1);
        $remotePath = $config['remote_path'] . '/' . $relativePath;

        if ($this->uploadFile($localPath, $remotePath)) {
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

      // Report results
      $this->disconnect();

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
        return Command::FAILURE;
      }

      return Command::SUCCESS;
    } catch (\Exception $e) {
      $output->writeln(sprintf('<error>Error: %s</error>', $e->getMessage()));
      $this->logger->error('Upload failed', ['error' => $e->getMessage()]);
      $this->disconnect();
      return Command::FAILURE;
    }
  }

  /**
   * Load and validate configuration
   *
   * @param InputInterface $input
   * @return array<string, mixed>
   * @throws \RuntimeException
   */
  protected function loadConfiguration(InputInterface $input): array
  {
    // Get input directory
    $inputDir = $input->getOption('input') ?? $this->container->getVariable('OUTPUT_DIR');
    if (!$inputDir) {
      throw new \RuntimeException('No input directory specified (use --input or set OUTPUT_DIR in .env)');
    }

    if (!is_dir($inputDir)) {
      throw new \RuntimeException(sprintf('Input directory does not exist: %s', $inputDir));
    }

    if (!is_readable($inputDir)) {
      throw new \RuntimeException(sprintf('Input directory is not readable: %s', $inputDir));
    }

    // Get SFTP configuration
    $host = $this->container->getVariable('SFTP_HOST');
    $port = (int)($this->container->getVariable('SFTP_PORT') ?? 22);
    $username = $this->container->getVariable('SFTP_USERNAME');
    $password = $this->container->getVariable('SFTP_PASSWORD');
    $keyPath = $this->container->getVariable('SFTP_PRIVATE_KEY_PATH');
    $keyPassphrase = $this->container->getVariable('SFTP_PRIVATE_KEY_PASSPHRASE');
    $remotePath = $this->container->getVariable('SFTP_REMOTE_PATH');

    // Validate required settings
    if (!$host) {
      throw new \RuntimeException('SFTP_HOST not configured in .env');
    }

    if (!$username) {
      throw new \RuntimeException('SFTP_USERNAME not configured in .env');
    }

    if (!$remotePath) {
      throw new \RuntimeException('SFTP_REMOTE_PATH not configured in .env');
    }

    // Validate authentication method
    if (!$password && !$keyPath) {
      throw new \RuntimeException('Either SFTP_PASSWORD or SFTP_PRIVATE_KEY_PATH must be configured');
    }

    return [
      'input_dir' => $inputDir,
      'host' => $host,
      'port' => $port,
      'username' => $username,
      'password' => $password,
      'key_path' => $keyPath,
      'key_passphrase' => $keyPassphrase,
      'remote_path' => rtrim($remotePath, '/'),
    ];
  }

  /**
   * Establish SFTP connection with authentication
   *
   * @param array<string, mixed> $config
   * @return bool
   */
  protected function connectSftp(array $config): bool
  {
    try {
      $this->sftp = new SFTP($config['host'], $config['port']);

      // Try key-based authentication first if configured
      if (!empty($config['key_path'])) {
        if ($this->authenticateWithKey($config['key_path'], $config['key_passphrase'])) {
          $this->logger->info('Connected via SSH key authentication');
          return true;
        }
      }

      // Fall back to password authentication
      if (!empty($config['password'])) {
        if ($this->authenticateWithPassword($config['username'], $config['password'])) {
          $this->logger->info('Connected via password authentication');
          return true;
        }
      }

      $this->logger->error('Authentication failed');
      return false;
    } catch (\Exception $e) {
      $this->logger->error('SFTP connection failed', ['error' => $e->getMessage()]);
      return false;
    }
  }

  /**
   * Authenticate with SSH private key
   *
   * @param string $keyPath
   * @param string|null $passphrase
   * @return bool
   */
  protected function authenticateWithKey(string $keyPath, ?string $passphrase): bool
  {
    try {
      if (!file_exists($keyPath)) {
        $this->logger->error('Private key file not found', ['path' => $keyPath]);
        return false;
      }

      $keyContent = file_get_contents($keyPath);
      if ($keyContent === false) {
        $this->logger->error('Failed to read private key file', ['path' => $keyPath]);
        return false;
      }

      $key = PublicKeyLoader::load($keyContent, $passphrase ?? false);

      return $this->sftp->login($this->container->getVariable('SFTP_USERNAME'), $key);
    } catch (\Exception $e) {
      $this->logger->error('Key authentication failed', ['error' => $e->getMessage()]);
      return false;
    }
  }

  /**
   * Authenticate with password
   *
   * @param string $username
   * @param string $password
   * @return bool
   */
  protected function authenticateWithPassword(string $username, string $password): bool
  {
    try {
      return $this->sftp->login($username, $password);
    } catch (\Exception $e) {
      $this->logger->error('Password authentication failed', ['error' => $e->getMessage()]);
      return false;
    }
  }

  /**
   * Ensure remote directory exists, create if needed
   *
   * @param string $path
   * @return bool
   */
  protected function ensureRemoteDirectory(string $path): bool
  {
    try {
      if ($this->sftp->is_dir($path)) {
        return true;
      }

      // Create directory recursively
      return $this->sftp->mkdir($path, -1, true);
    } catch (\Exception $e) {
      $this->logger->error('Failed to create remote directory', [
        'path' => $path,
        'error' => $e->getMessage()
      ]);
      return false;
    }
  }

  /**
   * Get recursive list of files to upload
   *
   * @param string $directory
   * @return array<int, string>
   */
  protected function getFilesToUpload(string $directory): array
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
      $this->logger->error('Failed to scan directory', [
        'directory' => $directory,
        'error' => $e->getMessage()
      ]);
    }

    return $files;
  }

  /**
   * Upload a single file
   *
   * @param string $localPath
   * @param string $remotePath
   * @return bool
   */
  protected function uploadFile(string $localPath, string $remotePath): bool
  {
    try {
      // Ensure remote directory exists
      $remoteDir = dirname($remotePath);
      if (!$this->sftp->is_dir($remoteDir)) {
        if (!$this->sftp->mkdir($remoteDir, -1, true)) {
          $this->logger->error('Failed to create remote directory', ['path' => $remoteDir]);
          return false;
        }
      }

      // Upload file
      $result = $this->sftp->put($remotePath, $localPath, SFTP::SOURCE_LOCAL_FILE);

      if (!$result) {
        $this->logger->error('Failed to upload file', [
          'local' => $localPath,
          'remote' => $remotePath
        ]);
        return false;
      }

      return true;
    } catch (\Exception $e) {
      $this->logger->error('Upload error', [
        'local' => $localPath,
        'remote' => $remotePath,
        'error' => $e->getMessage()
      ]);
      return false;
    }
  }

  /**
   * Close SFTP connection cleanly
   */
  protected function disconnect(): void
  {
    if ($this->sftp !== null) {
      $this->sftp->disconnect();
      $this->sftp = null;
    }
  }
}
