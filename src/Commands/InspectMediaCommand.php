<?php

declare(strict_types=1);

namespace EICC\StaticForge\Commands;

use EICC\StaticForge\Services\MediaInspector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

class InspectMediaCommand extends Command
{
    protected static $defaultName = 'site:inspect-media';
    protected static $defaultDescription = 'Inspect media file referenced in markdown frontmatter and update metadata';

    private MediaInspector $mediaInspector;

    public function __construct(?MediaInspector $mediaInspector = null)
    {
        parent::__construct();
        $this->mediaInspector = $mediaInspector ?? new MediaInspector();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Inspect media file referenced in markdown frontmatter and update metadata')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the markdown file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = $input->getArgument('file');

        if (!file_exists($filePath)) {
            $io->error("File not found: {$filePath}");
            return Command::FAILURE;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            $io->error("Failed to read file: {$filePath}");
            return Command::FAILURE;
        }

        // Parse Frontmatter
        if (!preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
            $io->error("No valid YAML frontmatter found in {$filePath}");
            return Command::FAILURE;
        }

        $frontmatterRaw = $matches[1];
        $body = $matches[2];

        try {
            $frontmatter = Yaml::parse($frontmatterRaw);
        } catch (\Exception $e) {
            $io->error("Failed to parse YAML frontmatter: " . $e->getMessage());
            return Command::FAILURE;
        }

        // Find media file
        $mediaFile = $frontmatter['audio_file'] ?? $frontmatter['video_file'] ?? null;

        if (!$mediaFile) {
            $io->error("No 'audio_file' or 'video_file' found in frontmatter.");
            return Command::FAILURE;
        }

        $io->section("Inspecting Media: {$mediaFile}");

        $tempFile = null;
        $inspectPath = '';

        // Check if remote
        if (preg_match('~^https?://~i', $mediaFile)) {
            $io->text("Remote file detected. Downloading...");
            $tempFile = tempnam(sys_get_temp_dir(), 'sf_media_');

            if (!$this->downloadFile($mediaFile, $tempFile, $io)) {
                return Command::FAILURE;
            }
            $inspectPath = $tempFile;
        } else {
            // Local file
            // Try relative to content dir first, then public/assets, then absolute
            $candidates = [
                'content/' . ltrim($mediaFile, '/'),
                'public/' . ltrim($mediaFile, '/'),
                $mediaFile
            ];

            foreach ($candidates as $candidate) {
                if (file_exists($candidate)) {
                    $inspectPath = $candidate;
                    break;
                }
            }

            if (!$inspectPath) {
                $io->error("Local media file not found. Checked: " . implode(', ', $candidates));
                return Command::FAILURE;
            }
        }

        try {
            $metadata = $this->mediaInspector->inspect($inspectPath);

            $io->success("Analysis Complete:");
            $io->table(
                ['Property', 'Value'],
                [
                    ['Size', number_format($metadata['size']) . ' bytes'],
                    ['Type', $metadata['type']],
                    ['Duration', $metadata['duration']],
                ]
            );

            // Update Frontmatter
            $frontmatter['audio_size'] = $metadata['size'];
            $frontmatter['audio_type'] = $metadata['type'];
            $frontmatter['itunes_duration'] = $metadata['duration'];

            // Reconstruct File
            $newYaml = Yaml::dump($frontmatter, 4, 2); // 4 inline level, 2 spaces indent
            $newContent = "---\n{$newYaml}---\n{$body}";

            // Backup original
            copy($filePath, $filePath . '.bak');

            file_put_contents($filePath, $newContent);

            $io->success("Updated {$filePath} (Backup created at {$filePath}.bak)");

        } catch (\Exception $e) {
            $io->error("Analysis failed: " . $e->getMessage());
            return Command::FAILURE;
        } finally {
            if ($tempFile && file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        return Command::SUCCESS;
    }

    private function downloadFile(string $url, string $dest, SymfonyStyle $io): bool
    {
        $fp = fopen($dest, 'w+');
        if ($fp === false) {
            $io->error("Could not open temporary file for writing.");
            return false;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);

        // Progress bar
        $progressBar = $io->createProgressBar();
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function ($resource, $downloadSize, $downloaded, $uploadSize, $uploaded) use ($progressBar) {
            if ($downloadSize > 0) {
                $progressBar->setMaxSteps($downloadSize);
                $progressBar->setProgress($downloaded);
            }
        });

        $io->text("Downloading...");
        $success = curl_exec($ch);
        $progressBar->finish();
        $io->newLine();

        if (!$success) {
            $io->error("Download failed: " . curl_error($ch));
            fclose($fp);
            return false;
        }

        curl_close($ch);
        fclose($fp);
        return true;
    }
}
