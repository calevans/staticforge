<?php

declare(strict_types=1);

namespace EICC\StaticForge\Commands\Audit;

use EICC\Utils\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use InvalidArgumentException;

class LinksCommand extends Command
{
    protected static $defaultName = 'audit:links';
    protected static $defaultDescription = 'Validate internal and external links in the generated site';

    protected Container $container;
    protected HttpClientInterface $httpClient;
    protected string $outputDir;
    protected SymfonyStyle $io;

    protected ?string $targetBaseUrl = null;
    protected ?string $transportUrl = null;

    public function __construct(Container $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function configure(): void
    {
        $this->setDescription('Validate internal and external links in the generated site')
            ->addOption('internal', 'i', InputOption::VALUE_NONE, 'Check internal links only')
            ->addOption('external', 'e', InputOption::VALUE_NONE, 'Check external links only')
            ->addOption('concurrency', 'c', InputOption::VALUE_OPTIONAL, 'Number of concurrent external requests', '10')
            ->addOption('url', 'u', InputOption::VALUE_OPTIONAL, 'Override the Base URL for checks (e.g. http://localhost:8000)', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Link Audit');

        $this->outputDir = $this->container->getVariable('OUTPUT_DIR') ?? 'public';

        // 1. Determine Target Base URL (The "Official" URL)
        $cliUrl = $input->getOption('url');
        if ($cliUrl) {
             $this->targetBaseUrl = $cliUrl;
        } else {
             $this->targetBaseUrl = $this->container->getVariable('SITE_BASE_URL') ?? 'http://localhost';
        }
        $this->targetBaseUrl = rtrim($this->targetBaseUrl, '/');

        // 2. Determine Transport URL (Where we send requests)
        if ($cliUrl) {
            $this->transportUrl = $this->targetBaseUrl;
            $this->io->note("Using provided CLI URL for checks: {$this->transportUrl}");
        } else {
            if ($this->isDevServerRunning()) {
                $this->transportUrl = 'http://localhost:8000';
                $this->io->note("Detected Dev Server at {$this->transportUrl}. Redirecting internal link checks to local server.");
            } else {
                $this->transportUrl = $this->targetBaseUrl;
                $this->io->note("Checking links against configured base URL: {$this->targetBaseUrl}");
            }
        }

        // Ensure absolute path
        if (!str_starts_with($this->outputDir, '/')) {
            $this->outputDir = (string)realpath($this->outputDir);
        }

        if (!is_dir($this->outputDir)) {
            $this->io->error("Output directory not found: {$this->outputDir}");
            $this->io->note("Run 'site:render' (or similar build command) before auditing links.");
            return Command::FAILURE;
        }

        $checkInternal = $input->getOption('internal');
        $checkExternal = $input->getOption('external');

        if (!$checkInternal && !$checkExternal) {
            $checkInternal = true;
            $checkExternal = true;
        }

        $allErrors = [];

        // 1. Scan for HTML files
        $htmlFiles = $this->findHtmlFiles($this->outputDir);
        $this->io->info(sprintf("Found %d HTML files to scan in %s", count($htmlFiles), $this->outputDir));

        $totalLinks = 0;
        $externalUrls = [];
        $internalChecks = [];

        $this->io->section('Scanning Files');
        $progressBar = $this->io->createProgressBar(count($htmlFiles));
        $progressBar->start();

        foreach ($htmlFiles as $filePath) {
            $html = file_get_contents($filePath);
            if ($html === false) continue;

            $crawler = new Crawler($html);
            $baseHref = null;
            $baseNode = $crawler->filter('base')->first();
            if ($baseNode->count() > 0) {
                $baseHref = $baseNode->attr('href');
            }

            $links = $crawler->filter('a')->extract(['href']);

            foreach ($links as $href) {
                if (empty($href) || str_starts_with($href, '#') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
                    continue;
                }

                $totalLinks++;

                if ($this->isExternal($href)) {
                    if ($checkExternal) {
                        $externalUrls[$href][] = $filePath;
                    }
                } elseif ($checkInternal) {
                    $internalChecks[] = [
                        'file' => $filePath,
                        'href' => $href,
                        'base' => $baseHref
                    ];
                }
            }
            $progressBar->advance();
        }
        $progressBar->finish();
        $this->io->newLine();

        // 2. Validate Internal Links
        if ($checkInternal && !empty($internalChecks)) {
            $this->io->section('Checking Internal Links');

            $internalErrors = $this->validateInternalLinks($internalChecks);
            $allErrors = array_merge($allErrors, $internalErrors);

            if (empty($internalErrors)) {
                $this->io->success("Internal links valid.");
            } else {
                $this->io->warning(count($internalErrors) . " broken internal links found.");
            }
        }

        // 3. Validate External Links
        if ($checkExternal && !empty($externalUrls)) {
            $this->io->section(sprintf('Checking %d unique external links', count($externalUrls)));
            $concurrency = (int)$input->getOption('concurrency');

            $externalErrors = $this->validateExternalLinks(array_keys($externalUrls), $concurrency, $externalUrls);
            $allErrors = array_merge($allErrors, $externalErrors);

            if (empty($externalErrors)) {
                $this->io->success("External links valid.");
            } else {
                $this->io->warning(count($externalErrors) . " broken external links found.");
            }
        }

        // 4. Report
        if (empty($allErrors)) {
            $this->io->success("Link audit completed. No broken links found.");
            return Command::SUCCESS;
        }

        $this->io->section('Broken Links Report');

        $groupedErrors = [];
        foreach ($allErrors as $error) {
            $source = str_replace($this->outputDir . '/', '', $error['source']);
            $groupedErrors[$source][] = $error;
        }
        ksort($groupedErrors);

        foreach ($groupedErrors as $context => $errors) {
            $this->io->writeln("<fg=cyan;options=bold>{$context}</>");
            foreach ($errors as $error) {
                // Link errors are typically strictly errors, so we'll use red/ERROR style
                // or we could inspect logic. For now, links command mostly reports failures.
                $this->io->writeln("  <fg=red>[ERROR]</> Link: {$error['link']}");
                $this->io->writeln("          Reason: {$error['reason']}");
            }
            $this->io->writeln("");
        }

        return Command::FAILURE;
    }

    private function isDevServerRunning(): bool
    {
        $connection = @fsockopen('localhost', 8000, $errno, $errstr, 1);
        if (is_resource($connection)) {
            fclose($connection);
            return true;
        }
        return false;
    }

    private function findHtmlFiles(string $dir): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'html') {
                $files[] = $file->getPathname();
            }
        }
        return $files;
    }

    private function isExternal(string $href): bool
    {
        return str_starts_with($href, 'http://') || str_starts_with($href, 'https://');
    }

    /**
     * @param array<int, array{file: string, href: string}> $checks
     * @return array<int, array{source: string, link: string, reason: string}>
     */
    private function validateInternalLinks(array $checks): array
    {
        $errors = [];
        $progressBar = $this->io->createProgressBar(count($checks));
        $progressBar->start();

        $this->httpClient = HttpClient::create([
                'headers' => ['User-Agent' => 'StaticForge-LinkChecker/1.0'],
                'timeout' => 5,
                'verify_peer' => false,
        ]);

        $urlMap = [];
        $uniqueUrls = [];
        $transportMap = [];

        foreach ($checks as $item) {
            $sourceFile = $item['file'];
            $href = $item['href'];
            $baseParams = $item['base'] ?? null;

            // Resolve full URL (Target)
            $targetUrl = '';
            $relativePath = str_replace($this->outputDir, '', $sourceFile);

            // Use base tag if present
            if ($baseParams) {
                if (str_starts_with($href, '/')) {
                    $targetUrl = $this->targetBaseUrl . $href;
                } else {
                    $basePath = parse_url($baseParams, PHP_URL_PATH) ?? '/';
                    $basePath = rtrim($basePath, '/');
                    $targetUrl = $this->targetBaseUrl . $basePath . '/' . $href;
                }
            } else {
                if (str_starts_with($href, '/')) {
                    $targetUrl = $this->targetBaseUrl . $href;
                } else {
                    $dir = dirname($relativePath);
                    if ($dir === '/' || $dir === '\\') $dir = '';
                    $targetUrl = $this->targetBaseUrl . $dir . '/' . $href;
                }
            }

            // Map Target URL to Transport URL
            $transportRequestUrl = $targetUrl;

            if ($this->transportUrl && $this->transportUrl !== $this->targetBaseUrl) {
                if (str_starts_with($targetUrl, $this->targetBaseUrl)) {
                    $path = substr($targetUrl, strlen($this->targetBaseUrl));
                    $transportRequestUrl = $this->transportUrl . $path;
                }
            }

            if (!isset($uniqueUrls[$targetUrl])) {
                $uniqueUrls[$targetUrl] = $targetUrl;
                $transportMap[$transportRequestUrl] = $targetUrl;
            }
            $urlMap[$targetUrl][] = ['source' => $sourceFile, 'link' => $href];
        }

        $urlsToRequest = array_keys($transportMap);

        $responses = [];
        foreach ($urlsToRequest as $reqUrl) {
            $responses[$reqUrl] = $this->httpClient->request('GET', $reqUrl);
        }

        foreach ($this->httpClient->stream($responses) as $response => $chunk) {
            try {
                if ($chunk->isFirst()) {
                    $statusCode = $response->getStatusCode();
                    if ($statusCode >= 400) {
                        $reqUrl = $this->findUrlByResponse($responses, $response);
                        $originalUrl = $transportMap[$reqUrl] ?? $reqUrl;
                        if ($originalUrl && isset($urlMap[$originalUrl])) {
                            foreach ($urlMap[$originalUrl] as $context) {
                                $errors[] = [
                                    'source' => $context['source'],
                                    'link' => $context['link'],
                                    'reason' => "HTTP {$statusCode}"
                                ];
                            }
                        }
                        $response->cancel();
                    }
                }
            } catch (\Exception $e) {
                 $reqUrl = $this->findUrlByResponse($responses, $response);
                 $originalUrl = $transportMap[$reqUrl] ?? $reqUrl;
                 if ($originalUrl && isset($urlMap[$originalUrl])) {
                    foreach ($urlMap[$originalUrl] as $context) {
                        $errors[] = [
                            'source' => $context['source'],
                            'link' => $context['link'],
                            'reason' => $e->getMessage()
                        ];
                    }
                 }
            }
        }

        $progressBar->finish();
        $this->io->newLine();
        return $errors;
    }

    /**
     * @param array<string> $urls
     * @param int $concurrency
     * @param array<string, array<string>> $urlMap url => [source_files]
     * @return array<int, array{source: string, link: string, reason: string}>
     */
    private function validateExternalLinks(array $urls, int $concurrency, array $urlMap): array
    {
        $errors = [];
        $this->httpClient = HttpClient::create([
            'headers' => ['User-Agent' => 'StaticForge-LinkChecker/1.0'],
            'timeout' => 5,
            'max_redirects' => 3,
            'verify_peer' => false,
        ]);

        $responses = [];
        foreach ($urls as $url) {
            $responses[$url] = $this->httpClient->request('GET', $url);
        }

        $progressBar = $this->io->createProgressBar(count($urls));
        $progressBar->start();

        foreach ($this->httpClient->stream($responses) as $response => $chunk) {
            try {
                if ($chunk->isTimeout()) {
                    $u = $this->findUrlByResponse($responses, $response);
                    if ($u) $this->addError($errors, $u, 'Timeout', $urlMap);
                    $response->cancel();
                }
                if ($chunk->isFirst()) {
                    $statusCode = $response->getStatusCode();
                    if ($statusCode >= 400) {
                        $u = $this->findUrlByResponse($responses, $response);
                        if ($u) $this->addError($errors, $u, "HTTP {$statusCode}", $urlMap);
                        $response->cancel();
                    }
                }
                if ($chunk->isLast()) {
                    $progressBar->advance();
                }
            } catch (\Exception $e) {
                 $u = $this->findUrlByResponse($responses, $response);
                 if ($u) $this->addError($errors, $u, "Connection Error: " . $e->getMessage(), $urlMap);
                 $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->io->newLine();
        return $errors;
    }

    private function findUrlByResponse(array $responses, $response): ?string
    {
        foreach ($responses as $url => $r) {
            if ($r === $response) return $url;
        }
        return null;
    }

    private function addError(array &$errors, string $url, string $reason, array $urlMap): void
    {
        foreach ($urlMap[$url] as $sourceFile) {
            $errors[] = [
                'source' => $sourceFile,
                'link' => $url,
                'reason' => $reason
            ];
        }
    }
}
