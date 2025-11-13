#!/usr/bin/env php
<?php
/**
 * Convert INI format frontmatter to YAML in PHP test files
 */

$directory = $argv[1] ?? 'tests/Integration';

$iterator = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
);

$count = 0;
foreach ($iterator as $file) {
  if ($file->getExtension() !== 'php') {
    continue;
  }

  $content = file_get_contents($file->getPathname());
  $originalContent = $content;

  // Replace INI format with YAML format in heredoc strings
  $content = preg_replace_callback(
    '/<<<[\'"]?(MD|HTML)[\'"]?\s*\n---\s*\n(.*?)\n---\s*\n/s',
    function ($matches) {
      $delimiter = $matches[1];
      $frontmatter = $matches[2];

      // Convert INI to YAML
      $yamlLines = [];
      $lines = explode("\n", $frontmatter);

      foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) {
          continue;
        }

        // Convert = to :
        if (preg_match('/^(\w+)\s*=\s*(.+)$/', $line, $m)) {
          $key = $m[1];
          $value = trim($m[2]);

          // Handle arrays: tags = [item1, item2]
          if (preg_match('/^\[(.+)\]$/', $value, $arrayMatch)) {
            $items = array_map('trim', explode(',', $arrayMatch[1]));
            $yamlLines[] = "$key:";
            foreach ($items as $item) {
              $yamlLines[] = "  - $item";
            }
          } else {
            $yamlLines[] = "$key: $value";
          }
        }
      }

      $yaml = implode("\n", $yamlLines);
      return "<<<'$delimiter'\n---\n$yaml\n---\n";
    },
    $content
  );

  // Update HTML comment format: <!-- INI ... --> to <!-- --- ... --- -->
  $content = preg_replace_callback(
    '/<<<[\'"]?(HTML)[\'"]?\s*\n<!--\s*INI\s*\n(.*?)\n-->\s*\n/s',
    function ($matches) {
      $delimiter = $matches[1];
      $frontmatter = $matches[2];

      // Convert INI to YAML
      $yamlLines = [];
      $lines = explode("\n", $frontmatter);

      foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) {
          continue;
        }

        // Convert = to :
        if (preg_match('/^(\w+)\s*=\s*(.+)$/', $line, $m)) {
          $key = $m[1];
          $value = trim($m[2]);

          $yamlLines[] = "$key: $value";
        }
      }

      $yaml = implode("\n", $yamlLines);
      return "<<<'$delimiter'\n<!--\n---\n$yaml\n---\n-->\n";
    },
    $content
  );

  if ($content !== $originalContent) {
    file_put_contents($file->getPathname(), $content);
    echo "Updated: {$file->getPathname()}\n";
    $count++;
  }
}

echo "\nTotal files updated: $count\n";
