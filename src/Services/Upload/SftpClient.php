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
     * Get the active SFTP connection, asserting it has been established
     *
     * @throws \RuntimeException If connect() has not been called successfully
     */
    private function getSftp(): SFTP
    {
        if ($this->sftp === null) {
            throw new \RuntimeException('SFTP connection has not been established. Call connect() first.');
        }

        return $this->sftp;
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

            if (!$this->verifyHostKey($this->sftp, $config)) {
                $this->sftp = null;
                return false;
            }

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
     * Trust-on-first-use host key verification. Called immediately after the
     * SSH handshake and before any credentials are sent, so a host that fails
     * verification never sees them:
     *
     * - An explicit SFTP_HOST_KEY config value pins and overrides the store.
     * - No stored key yet: trust this connection, record the key, continue.
     * - Stored key present and it doesn't match: fail closed - possible MITM.
     *
     * @param array<string, mixed> $config
     */
    private function verifyHostKey(SFTP $sftp, array $config): bool
    {
        $presented = $sftp->getServerPublicHostKey();
        if ($presented === false) {
            $this->logger->log('ERROR', 'SFTP handshake failed - could not retrieve server host key');
            return false;
        }

        if (!empty($config['host_key'])) {
            if ($presented !== $config['host_key']) {
                $this->logger->log('ERROR', 'SFTP host key does not match configured SFTP_HOST_KEY', [
                    'host' => $config['host'],
                ]);
                $sftp->disconnect();
                return false;
            }
            return true;
        }

        $store = new KnownHostsStore($config['known_hosts_path']);
        $stored = $store->getStoredKey($config['host'], $config['port']);

        if ($stored === null) {
            $this->logger->log('INFO', 'First connection to this SFTP host - trusting and recording its host key', [
                'host' => $config['host'],
            ]);
            $store->remember($config['host'], $config['port'], $presented);
            return true;
        }

        if ($stored !== $presented) {
            $this->logger->log('ERROR', 'SFTP host key changed since last connection - possible MITM, refusing', [
                'host' => $config['host'],
            ]);
            $sftp->disconnect();
            return false;
        }

        return true;
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

            if (!$this->getSftp()->login($username, $key)) {
                $this->logger->log('ERROR', 'Login failed with key', [
                    'username' => $username,
                    'errors' => $this->getSftp()->getErrors() ?: 'Unknown error'
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
            return $this->getSftp()->login($username, $password);
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
            if ($this->getSftp()->is_dir($path)) {
                return true;
            }

            // Create directory recursively
            return $this->getSftp()->mkdir($path, -1, true);
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
            if (!$this->getSftp()->is_dir($remoteDir)) {
                if (!$this->getSftp()->mkdir($remoteDir, -1, true)) {
                    $this->logger->log('ERROR', 'Failed to create remote directory', ['path' => $remoteDir]);
                    return false;
                }
            }

            // Upload file
            $result = $this->getSftp()->put($remotePath, $localPath, SFTP::SOURCE_LOCAL_FILE);

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
            return $this->getSftp()->file_exists($remotePath);
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
            if (!$this->getSftp()->file_exists($remotePath)) {
                return null;
            }
            $content = $this->getSftp()->get($remotePath);
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
            if (!$this->getSftp()->file_exists($remotePath)) {
                return true; // Already gone
            }
            return $this->getSftp()->delete($remotePath);
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
            return $this->getSftp()->put($remotePath, $content);
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
