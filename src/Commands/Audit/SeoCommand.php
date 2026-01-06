<?php

declare(strict_types=1);

namespace EICC\StaticForge\Commands\Audit;

use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use SplFileInfo;

class SeoCommand extends Command
{
    protected static $defaultName = 'audit:seo';
    protected static $defaultDescription = 'Validate SEO metadata and best practices';

    protected Container $container;
    protected string $outputDir;
    protected SymfonyStyle $io;

    // Tracking for uniqueness
    protected array $titles = [];
    protected array $descriptions = [];

    public function __construct(Container $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function configure(): void
    {
        $this->setDescription('Validate SEO metadata and best practices')
            ->addOption('min-title', null, InputOption::VALUE_OPTIONAL, 'Minimum title length', 10)
            ->addOption('max-title', null, InputOption::VALUE_OPTIONAL, 'Maximum title length', 60)
            ->addOption('min-desc', null, InputOption::VALUE_OPTIONAL, 'Minimum description length', 50)
            ->addOption('max-desc', null, InputOption::VALUE_OPTIONAL, 'Maximum description length', 160);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('SEO Audit');

        $this->outputDir = $this->container->getVariable('OUTPUT_DIR') ?? 'public';

        // Ensure absolute path
        if (!str_starts_with($this->outputDir, '/')) {
            $this->outputDir = (string)realpath($this->outputDir);
        }

        if (!is_dir($this->outputDir)) {
            $this->io->error("Output directory not found: {$this->outputDir}");
            return Command::FAILURE;
        }

        $htmlFiles = $this->findHtmlFiles($this->outputDir);
        $this->io->note(sprintf('Found %d HTML files to audit.', count($htmlFiles)));

        $issues = [];
        $errors = 0;
        $warnings = 0;

        foreach ($htmlFiles as $file) {
            $relPath = str_replace($this->outputDir . '/', '', $file->getPathname());
            $content = file_get_contents($file->getPathname());
            // Filter out empty files
            if (empty($content)) {
                $issues[] = ['file' => $relPath, 'type' => 'error', 'message' => 'File is empty'];
                $errors++;
                continue;
            }

            $crawler = new Crawler($content);
            $fileIssues = $this->auditFile($crawler, $relPath, $input);

            foreach ($fileIssues as $issue) {
                $issues[] = $issue;
                if ($issue['type'] === 'error') {
                    $errors++;
                } else {
                    $warnings++;
                }
            }
        }

        // Global Checks
        $this->io->section('Global Checks');

        if (!file_exists($this->outputDir . '/sitemap.xml')) {
            $this->io->error('sitemap.xml not found in output directory.');
            $errors++;
        } else {
            $this->io->success('sitemap.xml found.');
        }

        if (!file_exists($this->outputDir . '/robots.txt')) {
            $this->io->warning('robots.txt not found in output directory.');
            $warnings++;
        } else {
            $this->io->success('robots.txt found.');
        }

        // Uniqueness Checks
        $this->io->section('Uniqueness Checks');

        // Check Duplicate Titles
        foreach ($this->titles as $title => $files) {
            if (count($files) > 1) {
                // Shorten title for display
                $displayTitle = strlen($title) > 50 ? substr($title, 0, 47) . '...' : $title;
                $this->io->warning(
                    sprintf('Duplicate Title "%s" found in files: %s', $displayTitle, implode(', ', $files))
                );
                // Duplicates are warnings generally, but could be strictly enforced.
                $warnings++;
            }
        }

        // Check Duplicate Descriptions
        foreach ($this->descriptions as $desc => $files) {
            if (count($files) > 1) {
                // Shorten desc for display
                $displayDesc = strlen($desc) > 50 ? substr($desc, 0, 47) . '...' : $desc;
                $this->io->warning(
                    sprintf('Duplicate Description "%s" found in files: %s', $displayDesc, implode(', ', $files))
                );
                $warnings++;
            }
        }

        // Report
        if (!empty($issues)) {
            $this->io->section('Page Issues');

            // Group by file
            $groupedIssues = [];
            foreach ($issues as $issue) {
                $groupedIssues[$issue['file']][] = $issue;
            }
            ksort($groupedIssues);

            foreach ($groupedIssues as $file => $fileIssues) {
                $this->io->writeln("<fg=cyan;options=bold>{$file}</>");
                foreach ($fileIssues as $issue) {
                    $typeColor = $issue['type'] === 'error' ? 'red' : 'yellow';
                    $typeLabel = strtoupper($issue['type']);
                    // Pad the type label for alignment if desired, but simple is fine
                    $this->io->writeln("  <fg={$typeColor}>[{$typeLabel}]</> {$issue['message']}");
                }
                $this->io->writeln(""); // Empty line separator
            }
        }

        $this->io->section('Audit Summary');
        $this->io->text("Files Scanned: " . count($htmlFiles));
        $this->io->text("Errors: $errors");
        $this->io->text("Warnings: $warnings");

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    protected function auditFile(Crawler $crawler, string $filename, InputInterface $input): array
    {
        $issues = [];

        // 1. Title
        try {
            $title = $crawler->filter('title')->text();

            // Clean title (remove newlines if multiline)
            $title = trim(preg_replace('/\s+/', ' ', $title));

            if ($title !== '') {
                $this->titles[$title][] = $filename;
            }

            $minTitle = (int)$input->getOption('min-title');
            $maxTitle = (int)$input->getOption('max-title');

            if (empty($title)) {
                $issues[] = ['file' => $filename, 'type' => 'error', 'message' => 'Title tag is empty'];
            } elseif (strlen($title) < $minTitle) {
                $issues[] = [
                    'file' => $filename,
                    'type' => 'warning',
                    'message' => "Title too short (< {$minTitle} chars) -> '{$title}'"
                ];
            } elseif (strlen($title) > $maxTitle) {
                $issues[] = [
                    'file' => $filename,
                    'type' => 'warning',
                    'message' => "Title too long (> {$maxTitle} chars)"
                ];
            }

            if (stripos($title, 'Document') !== false && strlen($title) < 15) {
                $issues[] = ['file' => $filename, 'type' => 'warning', 'message' => "Suspicious title '{$title}'"];
            }
        } catch (\Exception $e) {
             $issues[] = ['file' => $filename, 'type' => 'error', 'message' => 'Missing <title> tag'];
        }

        // 2. Meta Description
        try {
            // Some parsers might find multiple, we want just one valid one.
            $descNode = $crawler->filter('meta[name="description"]');

            if ($descNode->count() > 0) {
                 $description = $descNode->attr('content');

                if ($description) {
                    $description = trim(preg_replace('/\s+/', ' ', $description));
                    $this->descriptions[$description][] = $filename;

                    $minDesc = (int)$input->getOption('min-desc');
                    $maxDesc = (int)$input->getOption('max-desc');

                    if (strlen($description) < $minDesc) {
                        $issues[] = [
                            'file' => $filename,
                            'type' => 'warning',
                            'message' => "Description too short (< {$minDesc} chars)"
                        ];
                    } elseif (strlen($description) > $maxDesc) {
                        $issues[] = [
                            'file' => $filename,
                            'type' => 'warning',
                            'message' => "Description too long (> {$maxDesc} chars)"
                        ];
                    }
                } else {
                    $issues[] = [
                        'file' => $filename,
                        'type' => 'error',
                        'message' => 'meta description content is empty'
                    ];
                }
            } else {
                 $issues[] = [
                    'file' => $filename,
                    'type' => 'error',
                    'message' => 'Missing <meta name="description"> tag'
                 ];
            }
        } catch (\Exception $e) {
            // Should be caught by logic above but good for safety
            $issues[] = [
                'file' => $filename,
                'type' => 'error',
                'message' => 'Error checking description: ' . $e->getMessage()
            ];
        }

        // 3. Canonical
        try {
            $canonical = $crawler->filter('link[rel="canonical"]');
            if ($canonical->count() > 0) {
                if (!$canonical->attr('href')) {
                    $issues[] = [
                        'file' => $filename,
                        'type' => 'warning',
                        'message' => 'Empty canonical link href'
                    ];
                }
            } else {
                 $issues[] = [
                    'file' => $filename,
                    'type' => 'warning',
                    'message' => 'Missing <link rel="canonical"> tag'
                 ];
            }
        } catch (\Exception $e) {
            $issues[] = [
                'file' => $filename,
                'type' => 'warning',
                'message' => 'Missing <link rel="canonical"> tag'
            ];
        }

        // 4. Fire SEO_AUDIT_PAGE event for plugins to add their own checks
        $eventData = [
            'crawler' => $crawler,
            'filename' => $filename,
            'issues' => $issues
        ];

        try {
            /** @var EventManager $eventManager */
            $eventManager = $this->container->get(EventManager::class);
            $result = $eventManager->fire('SEO_AUDIT_PAGE', $eventData);

            if (isset($result['issues']) && is_array($result['issues'])) {
                $issues = $result['issues'];
            }
        } catch (\Exception $e) {
            $issues[] = [
                'file' => $filename,
                'type' => 'error',
                'message' => 'Event SEO_AUDIT_PAGE failed: ' . $e->getMessage()
            ];
        }

        return $issues;
    }

    protected function findHtmlFiles(string $directory): array
    {
        $directoryIterator = new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($directoryIterator);
        $htmlFiles = [];

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'html') {
                $htmlFiles[] = $file;
            }
        }

        return $htmlFiles;
    }
}
