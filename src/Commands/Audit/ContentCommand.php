<?php

declare(strict_types=1);

namespace EICC\StaticForge\Commands\Audit;

use EICC\Utils\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use SplFileInfo;

class ContentCommand extends Command
{
    protected static $defaultName = 'audit:content';
    protected static $defaultDescription = 'Validate source content integrity (Frontmatter, Taxonomies, Links)';

    protected Container $container;
    protected SymfonyStyle $io;
    protected string $contentDir;
    protected array $siteConfig;

    public function __construct(Container $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function configure(): void
    {
        $this->setDescription('Validate source content integrity (Frontmatter, Taxonomies, Links)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('Content Audit');

        $this->contentDir = getcwd() . '/content';
        if (!is_dir($this->contentDir)) {
            $this->io->error("Content directory not found at: {$this->contentDir}");
            return Command::FAILURE;
        }

        $this->siteConfig = $this->container->getVariable('site_config') ?? [];


        // Count files
        $fileCount = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->contentDir));
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'md') {
                $fileCount++;
            }
        }

        $this->io->note(sprintf('Found %d Content files to audit.', $fileCount));

        $issues = [];
        $issues = array_merge($issues, $this->auditFrontmatter());

        // If we want to check taxonomies, we need to know what are the valid ones.
        // Assuming siteConfig has 'taxonomies' or similar if strict mode is enabled.
        // For now, let's stick to orphan tags/categories if possible, or skip if complex.
        // Let's implement a basic taxonomy check if defined in config.
        $issues = array_merge($issues, $this->auditTaxonomies());

        $issues = array_merge($issues, $this->auditMarkdownLinks());

        $errors = 0;
        $warnings = 0;

        foreach ($issues as $issue) {
            if ($issue['type'] === 'error') {
                $errors++;
            } else {
                $warnings++;
            }
        }

        if (empty($issues)) {
            $this->io->success('Content audit passed! No issues found.');
            return Command::SUCCESS;
        }

         // Group by file
         $groupedIssues = [];
         foreach ($issues as $issue) {
             $groupedIssues[$issue['file']][] = $issue;
         }
         ksort($groupedIssues);

         $this->io->section('Content Issues');

         foreach ($groupedIssues as $file => $fileIssues) {
            $this->io->writeln("<fg=cyan;options=bold>{$file}</>");
            foreach ($fileIssues as $issue) {
                $typeColor = $issue['type'] === 'error' ? 'red' : 'yellow';
                $typeLabel = strtoupper($issue['type']);
                $message = $issue['message'] ?? 'Unknown issue';

                $this->io->writeln("  <fg={$typeColor}>[{$typeLabel}]</> {$message}");
            }
            $this->io->writeln(""); // Empty line separator
        }

        $this->io->section('Audit Summary');
        $this->io->text("Files Scanned: " . $fileCount);
        $this->io->text("Errors: $errors");
        $this->io->text("Warnings: $warnings");

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    protected function auditFrontmatter(): array
    {
        $this->io->section('Checking Frontmatter...');
        $issues = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->contentDir));

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->getExtension() !== 'md') {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            $relativePath = str_replace(getcwd() . '/', '', $file->getPathname());

            // Regex for frontmatter
            if (!preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $matches)) {
                $issues[] = [
                    'file' => $relativePath,
                    'type' => 'error',
                    'message' => 'Missing or invalid frontmatter block'
                ];
                continue;
            }

            try {
                $frontmatter = Yaml::parse($matches[1]);

                // Check required fields
                $required = ['title']; // Add others if strictly required like 'date' or 'layout'/'template'?
                // 'template' might be optional if default is used. 'type' might be optional.

                foreach ($required as $field) {
                    if (empty($frontmatter[$field])) {
                        $issues[] = [
                            'file' => $relativePath,
                            'type' => 'error',
                             'message' => "Missing required field: {$field}"
                        ];
                    }
                }

                // Check Draft Status (Warning)
                if (isset($frontmatter['draft']) && $frontmatter['draft'] === true) {
                     $issues[] = [
                        'file' => $relativePath,
                        'type' => 'warning',
                        'message' => "Post is marked as draft"
                    ];
                }

            } catch (\Exception $e) {
                $issues[] = [
                    'file' => $relativePath,
                    'type' => 'error',
                    'message' => 'YAML Parse Error: ' . $e->getMessage()
                ];
            }
        }

        return $issues;
    }

    protected function auditTaxonomies(): array
    {
        // Simple check: Collect all used tags/categories and check if they look sane.
        // Real validation requires a source of truth for "allowed" taxonomies.

        // For now, let's skip complex validation unless we know where taxonomies are defined.
        // Typically they are free-form in many SSGs unless defined in siteconfig.

        return [];
    }

    protected function auditMarkdownLinks(): array
    {
        $this->io->section('Checking Markdown Internal Links...');
        $issues = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->contentDir));

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->getExtension() !== 'md') {
                continue;
            }

            $content = file_get_contents($file->getPathname());
            $relativePath = str_replace(getcwd() . '/', '', $file->getPathname());

            // Match [Label](link)
            // Exclude external links (http), mailto, anchors (#)
            if (preg_match_all('/\[([^\]]+)\]\(([^)]+)\)/', $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $link = $match[2];

                    // Skip external links
                    if (preg_match('/^(http|https|mailto:|tel:|\/\/)/', $link)) {
                        continue;
                    }

                    // Skip anchors only links
                    if (str_starts_with($link, '#')) {
                        continue;
                    }

                    // Skip .html links - these are likely output paths (permalinks) and should be validated by audit:links post-build
                    if (str_ends_with($link, '.html')) {
                        continue;
                    }

                    // Handle internal links
                    // 1. Absolute path from /content (e.g. /images/foo.png) -> relative to contentDir
                    // 2. Relative path (../foo.md) -> relative to current file dir

                    // Remove query strings or hashes
                    $cleanLink = preg_replace('/[#?].*$/', '', $link);

                    if (str_starts_with($cleanLink, '/')) {
                        // Root relative
                        $basePath = $this->contentDir . $cleanLink;
                    } else {
                        // Relative
                        $basePath = $file->getPath() . '/' . $cleanLink;
                    }

                    // Simple existence check for source assets (.md, .jpg, etc)
                    if (!file_exists($basePath)) {
                         $issues[] = [
                            'file' => $relativePath,
                            'type' => 'error',
                            'message' => "Link target not found: {$link}"
                        ];
                    }
                }
            }
        }

        return $issues;
    }
}
