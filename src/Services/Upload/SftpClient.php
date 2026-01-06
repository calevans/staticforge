<?php

declare(strict_types=1);

namespace EICC\StaticForge\Services\Upload;

use EICC\Utils\Log;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;

class SftpClient
{
    private Log $logger;
    private ?SFTP $sftp = null;

    public function __construct(Log $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Establish SFTP connection with authentication
     *
     * @param array<string, mixed> $config
     * @return bool
     */
    public function connect(array $config): bool
    {
        try {
            $this->logger->log('DEBUG', sprintf('Connecting to %s:%d', $config['host'], $config['port']));
            $this->sftp = new SFTP($config['host'], $config['port']);

            // Try key-based authentication first if configured
            if (!empty($config['key_path'])) {
                $this->logger->log('DEBUG', sprintf('Attempting key auth with: %s', $config['key_path']));
                if ($this->authenticateWithKey($config['key_path'], $config['key_passphrase'], $config['username'])) {
                    $this->logger->log('INFO', 'Connected via SSH key authentication');
                    return true;
                }
            }

            // Fall back to password authentication
            if (!empty($config['password'])) {
                $this->logger->log('DEBUG', 'Attempting password auth');
                if ($this->authenticateWithPassword($config['username'], $config['password'])) {
                    $this->logger->log('INFO', 'Connected via password authentication');
                    return true;
                }
            }

            $this->logger->log('ERROR', 'Authentication failed - No valid method succeeded');
            return false;
        } catch (\Exception $e) {
            $this->logger->log('ERROR', 'SFTP connection failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Authenticate with SSH private key
     */
    private function authenticateWithKey(string $keyPath, ?string $passphrase, string $username): bool
    {
        try {
            if (!file_exists($keyPath)) {
                $this->logger->log('ERROR', 'Private key file not found', ['path' => $keyPath]);
                return false;
            }

            $keyContent = file_get_contents($keyPath);
            if ($keyContent === false) {
                $this->logger->log('ERROR', 'Failed to read private key file', ['path' => $keyPath]);
                return false;
            }

            $this->logger->log('DEBUG', 'Loading private key...');
            $key = PublicKeyLoader::load($keyContent, $passphrase ?? '');

            if (!$key instanceof \phpseclib3\Crypt\Common\PrivateKey) {
                $this->logger->log('ERROR', 'Loaded key is not a private key');
                return false;
            }

            $this->logger->log('DEBUG', sprintf('Authenticating as user: %s', $username));

            if (!$this->sftp->login($username, $key)) {
                $this->logger->log('ERROR', 'Login failed with key', [
                    'username' => $username,
                    'errors' => $this->sftp->getErrors() ?: 'Unknown error'
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            $this->logger->log('ERROR', 'Key authentication failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Authenticate with password
     */
    private function authenticateWithPassword(string $username, string $password): bool
    {
        try {
            return $this->sftp->login($username, $password);
        } catch (\Exception $e) {
            $this->logger->log('ERROR', 'Password authentication failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Ensure remote directory exists, create if needed
     */
    public function ensureRemoteDirectory(string $path): bool
    {
        try {
            if ($this->sftp->is_dir($path)) {
                return true;
            }

            // Create directory recursively
            return $this->sftp->mkdir($path, -1, true);
        } catch (\Exception $e) {
            $this->logger->log('ERROR', 'Failed to create remote directory', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Upload a single file
     */
    public function uploadFile(string $localPath, string $remotePath): bool
    {
        try {
            // Ensure remote directory exists
            $remoteDir = dirname($remotePath);
            if (!$this->sftp->is_dir($remoteDir)) {
                if (!$this->sftp->mkdir($remoteDir, -1, true)) {
                    $this->logger->log('ERROR', 'Failed to create remote directory', ['path' => $remoteDir]);
                    return false;
                }
            }

            // Upload file
            $result = $this->sftp->put($remotePath, $localPath, SFTP::SOURCE_LOCAL_FILE);

            if (!$result) {
                $this->logger->log('ERROR', 'Failed to upload file', [
                    'local' => $localPath,
                    'remote' => $remotePath
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            $this->logger->log('ERROR', 'Upload error', [
                'local' => $localPath,
                'remote' => $remotePath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check if file exists on remote
     */
    public function fileExists(string $remotePath): bool
    {
        try {
            return $this->sftp->file_exists($remotePath);
        } catch (\Exception $e) {
            $this->logger->log('ERROR', 'Failed to check file existence', ['path' => $remotePath, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Read file content from remote
     */
    public function readFile(string $remotePath): ?string
    {
        try {
            if (!$this->sftp->file_exists($remotePath)) {
                return null;
            }
            $content = $this->sftp->get($remotePath);
            return $content === false ? null : (string)$content;
        } catch (\Exception $e) {
            $this->logger->log('ERROR', 'Failed to read file', ['path' => $remotePath, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Delete file from remote
     */
    public function deleteFile(string $remotePath): bool
    {
        try {
            if (!$this->sftp->file_exists($remotePath)) {
                return true; // Already gone
            }
            return $this->sftp->delete($remotePath);
        } catch (\Exception $e) {
            $this->logger->log('ERROR', 'Failed to delete file', ['path' => $remotePath, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Write string content directly to remote file
     */
    public function putContent(string $remotePath, string $content): bool
    {
        try {
            return $this->sftp->put($remotePath, $content);
        } catch (\Exception $e) {
            $this->logger->log('ERROR', 'Failed to write content', ['path' => $remotePath, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Close SFTP connection cleanly
     */
    public function disconnect(): void
    {
        if ($this->sftp !== null) {
            $this->sftp->disconnect();
            $this->sftp = null;
        }
    }
}
