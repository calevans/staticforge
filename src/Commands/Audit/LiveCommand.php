<?php

declare(strict_types=1);

namespace EICC\StaticForge\Commands\Audit;

use EICC\Utils\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class LiveCommand extends Command
{
    protected static $defaultName = 'audit:live';
    protected static $defaultDescription = 'Audit a live deployed site for security and best practices';

    protected Container $container;
    protected SymfonyStyle $io;

    public function __construct(Container $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function configure(): void
    {
        $this->setDescription('Audit a live deployed site for security and best practices')
            ->addOption('url', 'u', InputOption::VALUE_OPTIONAL, 'The live URL to audit (overrides UPLOAD_URL)', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Live Site Audit');

        $url = $input->getOption('url');

        if (!$url) {
            $envUrl = $this->container->getVariable('UPLOAD_URL');
            if ($envUrl && filter_var($envUrl, FILTER_VALIDATE_URL)) {
                $url = $envUrl;
            }
        }

        if (!$url) {
            $this->io->error("No URL provided. Use --url or ensure UPLOAD_URL is set in .env");
            return Command::FAILURE;
        }

        // Ensure trailing slash for consistency
        $url = rtrim($url, '/') . '/';

        $this->io->note("Auditing target: $url");

        if (!function_exists('curl_init')) {
            $this->io->error("The CURL PHP extension is required for this command.");
            return Command::FAILURE;
        }

        $issues = [];

        // Run Checks
        $issues = array_merge($issues, $this->checkConnectivityAndSsl($url));
        // Only proceed if we could actually connect
        if (empty($issues) || $issues[0]['type'] !== 'error') {
            $issues = array_merge($issues, $this->checkSecurityHeaders($url));
            $issues = array_merge($issues, $this->checkPerformanceHeaders($url));
            $issues = array_merge($issues, $this->checkDeploymentIntegrity($url));
        }

        // Output Results
        $errors = 0;
        $warnings = 0;
        $successes = 0;

        foreach ($issues as $issue) {
            if ($issue['type'] === 'error') {
                $errors++;
            } elseif ($issue['type'] === 'warning') {
                $warnings++;
            } elseif ($issue['type'] === 'success') {
                $successes++;
            }
        }

        if (empty($issues)) {
            $this->io->success("Site {$url} passed all live checks (but no checks were run?)");
            return Command::SUCCESS;
        }

        // Sort issues: Success > Warning > Error
        usort($issues, function ($a, $b) {
            $weights = ['success' => 1, 'warning' => 2, 'error' => 3];
            return ($weights[$a['type']] ?? 99) <=> ($weights[$b['type']] ?? 99);
        });

        $this->io->section('Live Audit Results');
        foreach ($issues as $issue) {
            $typeColor = match($issue['type']) {
                'error' => 'red',
                'warning' => 'yellow',
                'success' => 'green',
                default => 'white'
            };

            $typeLabel = strtoupper($issue['type']);
            // Pad label for alignment
            $typeLabel = str_pad($typeLabel, 7, ' ', STR_PAD_BOTH);

            // Format: [TYPE] [Scope] Message
            $scope = isset($issue['scope']) ? "[{$issue['scope']}] " : '';
            $this->io->writeln("  <fg={$typeColor}>[{$typeLabel}]</> {$scope}{$issue['message']}");
        }
        $this->io->newLine();

        $this->io->section('Audit Summary');
        $this->io->text("Errors: $errors");
        $this->io->text("Warnings: $warnings");
        $this->io->text("Passed Checks: $successes");

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Helper to perform CURL requests and parse headers
     */
    protected function performRequest(string $url, array $curlOptions = []): array
    {
        $ch = curl_init();

        $defaults = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => false,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
        ];

        // Apply defaults overriding with passed options if any
        foreach ($defaults as $opt => $val) {
            if (!isset($curlOptions[$opt])) {
                curl_setopt($ch, $opt, $val);
            }
        }
        foreach ($curlOptions as $opt => $val) {
            curl_setopt($ch, $opt, $val);
        }

        $response = curl_exec($ch);

        $result = [
            'success' => false,
            'code' => 0,
            'headers' => [],
            'body' => '',
            'error' => ''
        ];

        if ($response === false) {
            $result['error'] = curl_error($ch);
        } else {
            $result['success'] = true;
            $result['code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $headerText = substr($response, 0, $headerSize);
            $result['body'] = substr($response, $headerSize);

            // Parse headers
            foreach (explode("\n", $headerText) as $line) {
                if (str_contains($line, ':')) {
                    $parts = explode(':', $line, 2);
                    $key = strtolower(trim($parts[0]));
                    $value = trim($parts[1]);
                    // Support multiple headers with same name? For now just overwrite or array?
                    // Simple overwrite usually enough for these checks (except Set-Cookie)
                    if (isset($result['headers'][$key])) {
                        if (is_array($result['headers'][$key])) {
                            $result['headers'][$key][] = $value;
                        } else {
                            $result['headers'][$key] = [$result['headers'][$key], $value];
                        }
                    } else {
                        $result['headers'][$key] = $value;
                    }
                }
            }
        }

        curl_close($ch);
        return $result;
    }

    protected function checkConnectivityAndSsl(string $url): array
    {
        $issues = [];
        $this->io->text("  > Checking connectivity and SSL...");

        try {
            // Context for SSL info extraction
            $context = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);

            $urlParts = parse_url($url);
            $host = $urlParts['host'];
            $port = $urlParts['scheme'] === 'https' ? 443 : 80;

            // Use native socket to get Cert info easily, HttpClient abstracts this heavily
            if ($urlParts['scheme'] === 'https') {
                $client = stream_socket_client(
                    "ssl://{$host}:{$port}",
                    $errno,
                    $errstr,
                    10,
                    STREAM_CLIENT_CONNECT,
                    $context
                );

                if (!$client) {
                     $issues[] = ['type' => 'error', 'scope' => 'Connectivity', 'message' => "Could not connect to {$host}: $errstr"];
                     return $issues;
                }

                $params = stream_context_get_params($client);
                $cert = $params['options']['ssl']['peer_certificate'];
                $certInfo = openssl_x509_parse($cert);

                $validTo = $certInfo['validTo_time_t'];
                $daysUntilExpiry = round(($validTo - time()) / 86400);

                if ($daysUntilExpiry < 0) {
                     $issues[] = ['type' => 'error', 'scope' => 'SSL', 'message' => "SSL Certificate has expired!"];
                } elseif ($daysUntilExpiry < 14) {
                     $issues[] = ['type' => 'warning', 'scope' => 'SSL', 'message' => "SSL Certificate expires soon ({$daysUntilExpiry} days)."];
                } else {
                     $issues[] = ['type' => 'success', 'scope' => 'SSL', 'message' => "SSL Certificate is valid for {$daysUntilExpiry} days."];
                }
            } else {
                 $issues[] = ['type' => 'warning', 'scope' => 'SSL', 'message' => "Site is not using HTTPS."];
            }

        } catch (\Exception $e) {
             $issues[] = ['type' => 'error', 'scope' => 'Connectivity', 'message' => "Connection failed: " . $e->getMessage()];
        }

        return $issues;
    }

    protected function checkSecurityHeaders(string $url): array
    {
        $issues = [];
        $this->io->text("  > Checking security headers...");

        // Use HEAD request for checking headers
        $result = $this->performRequest($url, [CURLOPT_NOBODY => true]);

        if (!$result['success']) {
            // Connectivity error already reported likely
            return $issues;
        }

        $headers = $result['headers'];

        // 1. HSTS
        if (!isset($headers['strict-transport-security'])) {
            if (str_starts_with($url, 'https')) {
                $issues[] = ['type' => 'warning', 'scope' => 'Security', 'message' => "Missing 'Strict-Transport-Security' header."];
            }
        } else {
            $issues[] = ['type' => 'success', 'scope' => 'Security', 'message' => "'Strict-Transport-Security' header found."];
        }

        // 2. X-Content-Type-Options
        // Value might be array if duplicate headers, handle gracefully
        $val = $headers['x-content-type-options'] ?? null;
        if (is_array($val)) $val = $val[0];

        if (!$val || !str_contains(strtolower($val), 'nosniff')) {
            $issues[] = ['type' => 'warning', 'scope' => 'Security', 'message' => "Missing or invalid 'X-Content-Type-Options' header (should be 'nosniff')."];
        } else {
            $issues[] = ['type' => 'success', 'scope' => 'Security', 'message' => "'X-Content-Type-Options: nosniff' header found."];
        }

        // 3. X-Frame-Options
        if (!isset($headers['x-frame-options'])) {
            $issues[] = ['type' => 'warning', 'scope' => 'Security', 'message' => "Missing 'X-Frame-Options' header."];
        } else {
            $issues[] = ['type' => 'success', 'scope' => 'Security', 'message' => "'X-Frame-Options' header found."];
        }


        return $issues;
    }

    protected function checkPerformanceHeaders(string $url): array
    {
        $issues = [];
        $this->io->text("  > Checking performance headers...");

        // Specifically ask for encoding
        $result = $this->performRequest($url, [
            CURLOPT_NOBODY => true,
            CURLOPT_HTTPHEADER => ['Accept-Encoding: gzip, deflate, br']
        ]);

        if (!$result['success']) {
            $issues[] = ['type' => 'warning', 'scope' => 'Performance', 'message' => "Performance check failed: " . $result['error']];
            return $issues;
        }

        $headers = $result['headers'];

        // Content-Encoding
        $encoding = $headers['content-encoding'] ?? null;
        if (is_array($encoding)) $encoding = $encoding[0];

        if ($encoding && preg_match('/(gzip|deflate|br)/i', $encoding, $matches)) {
            $issues[] = ['type' => 'success', 'scope' => 'Performance', 'message' => "Content-Encoding enabled ({$matches[1]})."];
        } else {
            // Some servers don't return Content-Encoding for HEAD requests if the body is empty,
            // but for a main page URL there is usually valid content.
            // Let's degrade to warning.
            $issues[] = ['type' => 'warning', 'scope' => 'Performance', 'message' => "Content-Encoding missing or not detected (gzip/brotli)."];
        }

        // Cache-Control
        if (!isset($headers['cache-control'])) {
            $issues[] = ['type' => 'warning', 'scope' => 'Performance', 'message' => "Missing 'Cache-Control' header."];
        } else {
            $issues[] = ['type' => 'success', 'scope' => 'Performance', 'message' => "'Cache-Control' header found."];
        }

        // Cache Validation (ETag or Last-Modified)
        if (isset($headers['etag']) || isset($headers['last-modified'])) {
            $found = isset($headers['etag']) ? 'ETag' : 'Last-Modified';
            if (isset($headers['etag']) && isset($headers['last-modified'])) {
                $found = 'ETag and Last-Modified';
            }
            $issues[] = ['type' => 'success', 'scope' => 'Performance', 'message' => "Cache validator found ($found)."];
        } else {
            $issues[] = ['type' => 'warning', 'scope' => 'Performance', 'message' => "Missing Cache Validator (ETag or Last-Modified)."];
        }

        return $issues;
    }

    protected function checkDeploymentIntegrity(string $url): array
    {
        $issues = [];
        $this->io->text("  > Checking deployment integrity...");

        // 1. Robots.txt
        $resRobots = $this->performRequest($url . 'robots.txt', [CURLOPT_NOBODY => true]);
        if ($resRobots['code'] !== 200) {
             $issues[] = ['type' => 'warning', 'scope' => 'Integrity', 'message' => "robots.txt not found (Status: {$resRobots['code']})."];
        } else {
             $issues[] = ['type' => 'success', 'scope' => 'Integrity', 'message' => "robots.txt found."];
        }

        // 2. Sitemap.xml - Need body check or just content-type? Logic before was Content-Type
        $resSitemap = $this->performRequest($url . 'sitemap.xml', [CURLOPT_NOBODY => true]);
        if ($resSitemap['code'] !== 200) {
             $issues[] = ['type' => 'warning', 'scope' => 'Integrity', 'message' => "sitemap.xml not found (Status: {$resSitemap['code']})."];
        } else {
            // Check content type
            $type = $resSitemap['headers']['content-type'] ?? '';
            if (is_array($type)) $type = $type[0];

            if (!str_contains($type, 'xml')) {
                 $issues[] = ['type' => 'warning', 'scope' => 'Integrity', 'message' => "sitemap.xml has incorrect Content-Type: {$type}"];
            } else {
                 $issues[] = ['type' => 'success', 'scope' => 'Integrity', 'message' => "sitemap.xml found and appears valid."];
            }
        }

        return $issues;
    }
}
