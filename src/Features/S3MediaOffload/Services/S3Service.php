<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\S3MediaOffload\Services;

use Aws\S3\S3Client;
use EICC\Utils\Log;
use Exception;

class S3Service
{
    private S3Client $s3Client;
    private Log $logger;
    private string $bucket;
    private string $region;

    public function __construct(Log $logger, array $config)
    {
        $this->logger = $logger;
        $this->bucket = $config['bucket'] ?? '';
        $this->region = $config['region'] ?? 'us-east-1';

        if (empty($this->bucket)) {
            throw new Exception('S3 Bucket is not configured.');
        }

        $s3Config = [
            'version' => 'latest',
            'region'  => $this->region,
        ];

        if (!empty($config['key']) && !empty($config['secret'])) {
            $s3Config['credentials'] = [
                'key'    => $config['key'],
                'secret' => $config['secret'],
            ];
        }

        if (!empty($config['endpoint'])) {
            $s3Config['endpoint'] = $config['endpoint'];
            $s3Config['use_path_style_endpoint'] = true;
        }

        $this->s3Client = new S3Client($s3Config);
    }

    public function uploadFile(string $filePath, string $key, string $acl = 'public-read'): bool
    {
        if (!file_exists($filePath)) {
            $this->logger->log('ERROR', "File not found: $filePath");
            return false;
        }

        try {
            $this->s3Client->putObject([
                'Bucket' => $this->bucket,
                'Key'    => $key,
                'SourceFile' => $filePath,
                'ACL'    => $acl,
            ]);

            $this->logger->log('INFO', "Uploaded $filePath to s3://{$this->bucket}/$key");
            return true;
        } catch (Exception $e) {
            $this->logger->log('ERROR', "Failed to upload $filePath: " . $e->getMessage());
            return false;
        }
    }

    public function listFiles(string $prefix = ''): array
    {
        try {
            $results = $this->s3Client->getPaginator('ListObjectsV2', [
                'Bucket' => $this->bucket,
                'Prefix' => $prefix,
            ]);

            $files = [];
            foreach ($results as $result) {
                if (isset($result['Contents'])) {
                    foreach ($result['Contents'] as $object) {
                        $files[] = $object['Key'];
                    }
                }
            }

            return $files;
        } catch (Exception $e) {
            $this->logger->log('ERROR', "Failed to list files: " . $e->getMessage());
            return [];
        }
    }

    public function downloadFile(string $key, string $savePath): bool
    {
        try {
            $result = $this->s3Client->getObject([
                'Bucket' => $this->bucket,
                'Key'    => $key,
            ]);

            $directory = dirname($savePath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            file_put_contents($savePath, $result['Body']);
            $this->logger->log('INFO', "Downloaded s3://{$this->bucket}/$key to $savePath");
            return true;
        } catch (Exception $e) {
            $this->logger->log('ERROR', "Failed to download $key: " . $e->getMessage());
            return false;
        }
    }
}
