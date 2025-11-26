<?php

declare(strict_types=1);

namespace EICC\StaticForge\Services\Upload;

use EICC\Utils\Container;
use Symfony\Component\Console\Input\InputInterface;

class SftpConfigLoader
{
    /**
     * Load and validate configuration
     *
     * @param InputInterface $input
     * @param Container $container
     * @return array<string, mixed>
     * @throws \RuntimeException
     */
    public function load(InputInterface $input, Container $container): array
    {
        // Get input directory
        $inputDir = $input->getOption('input') ?? $container->getVariable('OUTPUT_DIR');
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
        $host = $container->getVariable('SFTP_HOST');
        $port = (int)($container->getVariable('SFTP_PORT') ?? 22);
        $username = $container->getVariable('SFTP_USERNAME');
        $password = $container->getVariable('SFTP_PASSWORD');
        $keyPath = $container->getVariable('SFTP_PRIVATE_KEY_PATH');

        // Expand tilde in key path if present
        if ($keyPath && str_starts_with($keyPath, '~/')) {
            $home = getenv('HOME') ?: getenv('USERPROFILE');
            if ($home) {
                $keyPath = $home . substr($keyPath, 1);
            }
        }

        $keyPassphrase = $container->getVariable('SFTP_PRIVATE_KEY_PASSPHRASE');
        $remotePath = $container->getVariable('SFTP_REMOTE_PATH');

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
}
