<?php

namespace EICC\StaticForge\Commands;

use EICC\Utils\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Command to migrate frontmatter from INI format to YAML format
 */
class MigrateSiteCommand extends Command
{
  protected static $defaultName = 'site:migrate';
  protected static $defaultDescription = 'Migrate frontmatter from INI format to YAML format';

  protected Container $container;

  public function __construct(Container $container)
  {
    parent::__construct();
    $this->container = $container;
  }

  protected function configure(): void
  {
    $this->setDescription('Migrate frontmatter from INI format to YAML format')
      ->addOption(
        'dry-run',
        'd',
        InputOption::VALUE_NONE,
        'Show what would be migrated without making changes'
      )
      ->addOption(
        'no-backup',
        null,
        InputOption::VALUE_NONE,
        'Skip creating backup files (not recommended)'
      )
      ->addOption(
        'directory',
        null,
        InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
        'Specific directory to migrate (can specify multiple times)',
        []
      );
  }

  protected function execute(InputInterface $input, OutputInterface $output): int
  {
    $output->writeln('<info>StaticForge Frontmatter Migration Tool</info>');
    $output->writeln('<info>Converting INI format to YAML format</info>');
    $output->writeln('');

    $dryRun = $input->getOption('dry-run');
    $noBackup = $input->getOption('no-backup');
    $directories = $input->getOption('directory');

    if ($dryRun) {
      $output->writeln('<comment>DRY RUN MODE - No files will be modified</comment>');
      $output->writeln('');
    }

    // Get directories to scan
    if (empty($directories)) {
      $directories = $this->getDefaultDirectories();
    }

    $stats = [
      'total' => 0,
      'migrated' => 0,
      'skipped' => 0,
      'errors' => 0,
      'already_yaml' => 0,
    ];

    foreach ($directories as $directory) {
      if (!is_dir($directory)) {
        $output->writeln("<error>Directory not found: {$directory}</error>");
        continue;
      }

      $output->writeln("<comment>Scanning directory: {$directory}</comment>");
      $this->migrateDirectory($directory, $output, $dryRun, $noBackup, $stats);
    }

    // Display summary
    $output->writeln('');
    $output->writeln('<info>Migration Summary:</info>');
    $output->writeln("  Total files scanned:    {$stats['total']}");
    $output->writeln("  Files migrated:         {$stats['migrated']}");
    $output->writeln("  Already YAML format:    {$stats['already_yaml']}");
    $output->writeln("  Skipped (no frontmatter): {$stats['skipped']}");
    $output->writeln("  Errors:                 {$stats['errors']}");

    if ($dryRun) {
      $output->writeln('');
      $output->writeln('<comment>This was a dry run. Run without --dry-run to apply changes.</comment>');
    }

    return Command::SUCCESS;
  }

  /**
   * Get default directories to migrate
   *
   * @return array<string>
   */
  protected function getDefaultDirectories(): array
  {
    $sourceDir = $this->container->getVariable('SOURCE_DIR') ?? 'content';
    $directories = [$sourceDir];

    // Add docs directory if it exists
    if (is_dir('docs')) {
      $directories[] = 'docs';
    }

    return $directories;
  }

  /**
   * Migrate all files in a directory recursively
   */
  protected function migrateDirectory(
    string $directory,
    OutputInterface $output,
    bool $dryRun,
    bool $noBackup,
    array &$stats
  ): void {
    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
      if ($file->isFile()) {
        $extension = $file->getExtension();
        if (in_array($extension, ['md', 'html', 'htm'])) {
          $stats['total']++;
          $this->migrateFile($file->getPathname(), $extension, $output, $dryRun, $noBackup, $stats);
        }
      }
    }
  }

  /**
   * Migrate a single file from INI to YAML format
   */
  protected function migrateFile(
    string $filePath,
    string $extension,
    OutputInterface $output,
    bool $dryRun,
    bool $noBackup,
    array &$stats
  ): void {
    $content = file_get_contents($filePath);
    if ($content === false) {
      $output->writeln("<error>Failed to read: {$filePath}</error>");
      $stats['errors']++;
      return;
    }

    $result = null;

    if ($extension === 'md') {
      $result = $this->migrateMarkdownFile($content);
    } else {
      $result = $this->migrateHtmlFile($content);
    }

    if ($result === null) {
      // No frontmatter found
      $stats['skipped']++;
      return;
    }

    if ($result === false) {
      // Already in YAML format
      $stats['already_yaml']++;
      $output->writeln("  <info>✓</info> Already YAML: {$filePath}");
      return;
    }

    // Migration needed
    $output->writeln("  <comment>→</comment> Migrating: {$filePath}");

    if (!$dryRun) {
      // Create backup if requested
      if (!$noBackup) {
        $backupPath = $filePath . '.backup';
        if (!copy($filePath, $backupPath)) {
          $output->writeln("    <error>Failed to create backup</error>");
          $stats['errors']++;
          return;
        }
      }

      // Write migrated content
      if (file_put_contents($filePath, $result) === false) {
        $output->writeln("    <error>Failed to write migrated content</error>");
        $stats['errors']++;
        return;
      }
    }

    $stats['migrated']++;
  }

  /**
   * Migrate Markdown file frontmatter
   *
   * @return string|false|null String = migrated content, false = already YAML, null = no frontmatter
   */
  protected function migrateMarkdownFile(string $content): string|false|null
  {
    // Check for frontmatter
    if (!preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
      return null; // No frontmatter
    }

    $frontmatter = $matches[1];
    $body = $matches[2];

    // Check if already YAML (contains : instead of =)
    if ($this->isYamlFormat($frontmatter)) {
      return false; // Already YAML
    }

    // Convert INI to YAML
    $yamlFrontmatter = $this->convertIniToYaml($frontmatter);

    return "---\n{$yamlFrontmatter}\n---\n{$body}";
  }

  /**
   * Migrate HTML file frontmatter
   *
   * @return string|false|null String = migrated content, false = already YAML, null = no frontmatter
   */
  protected function migrateHtmlFile(string $content): string|false|null
  {
    // Check for old INI format: <!-- INI ... -->
    if (preg_match('/^<!--\s*INI\s*\n(.*?)\n-->\s*\n(.*)$/s', $content, $matches)) {
      $frontmatter = $matches[1];
      $body = $matches[2];

      // Convert INI to YAML
      $yamlFrontmatter = $this->convertIniToYaml($frontmatter);

      return "<!--\n---\n{$yamlFrontmatter}\n---\n-->\n{$body}";
    }

    // Check for new YAML format: <!-- --- ... --- -->
    if (preg_match('/^<!--\s*\n---\s*\n(.*?)\n---\s*\n-->\s*\n/s', $content)) {
      return false; // Already YAML
    }

    return null; // No frontmatter
  }

  /**
   * Check if frontmatter is already in YAML format
   */
  protected function isYamlFormat(string $frontmatter): bool
  {
    // YAML uses : for key-value pairs, INI uses =
    // Simple heuristic: if we find : before = on most lines, it's YAML
    $lines = explode("\n", trim($frontmatter));
    $colonCount = 0;
    $equalsCount = 0;

    foreach ($lines as $line) {
      $line = trim($line);
      if (empty($line) || str_starts_with($line, '#')) {
        continue;
      }

      if (strpos($line, ':') !== false) {
        $colonCount++;
      }
      if (strpos($line, '=') !== false) {
        $equalsCount++;
      }
    }

    return $colonCount > $equalsCount;
  }

  /**
   * Convert INI format frontmatter to YAML format
   */
  protected function convertIniToYaml(string $iniContent): string
  {
    $metadata = $this->parseIniContent($iniContent);

    // Convert to YAML string
    $yaml = Yaml::dump($metadata, 2, 2);

    // Trim trailing newline
    return rtrim($yaml);
  }

  /**
   * Parse INI-format content into metadata array
   * (Copied from old FileDiscovery implementation)
   *
   * @return array<string, mixed>
   */
  protected function parseIniContent(string $iniContent): array
  {
    $metadata = [];

    if (empty($iniContent)) {
      return $metadata;
    }

    $lines = explode("\n", $iniContent);
    foreach ($lines as $line) {
      $line = trim($line);

      // Skip empty lines and lines without =
      if (empty($line) || strpos($line, '=') === false) {
        continue;
      }

      [$key, $value] = array_map('trim', explode('=', $line, 2));

      // Remove quotes if present
      $value = trim($value, '"\'');

      // Handle arrays in square brackets [item1, item2]
      if (preg_match('/^\[(.*)\]$/', $value, $arrayMatch)) {
        $value = array_map('trim', explode(',', $arrayMatch[1]));
      }

      $metadata[$key] = $value;
    }

    return $metadata;
  }
}
