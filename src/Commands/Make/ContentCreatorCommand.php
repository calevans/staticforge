<?php

declare(strict_types=1);

namespace EICC\StaticForge\Commands\Make;

use EICC\Utils\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ContentCreatorCommand extends Command
{
    protected static $defaultName = 'make:content';
    protected static $defaultDescription = 'Create a new content file with frontmatter';

    private Container $container;
    private SymfonyStyle $io;

    public function __construct(Container $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Create a new content file with frontmatter')
            ->addArgument('title', InputArgument::REQUIRED, 'The title of the content')
            ->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'The content type/subfolder (e.g., blog, docs)', '')
            ->addOption('date', 'd', InputOption::VALUE_OPTIONAL, 'The publish date (YYYY-MM-DD)', date('Y-m-d'))
            ->addOption('draft', 'D', InputOption::VALUE_NONE, 'Mark as draft');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $title = $input->getArgument('title');
        $type = $input->getOption('type');
        $date = $input->getOption('date');
        $isDraft = $input->getOption('draft');

        // 1. Determine Directory
        $baseDir = 'content';
        $targetDir = $baseDir . ($type ? '/' . $type : '');
        
        // Ensure directory exists
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                $this->io->error(sprintf('Directory "%s" was not created', $targetDir));
                return Command::FAILURE;
            }
        }

        // 2. Generate Filename
        $slug = $this->slugify($title);
        $filename = $slug . '.md';
        $filePath = $targetDir . '/' . $filename;

        if (file_exists($filePath)) {
            $this->io->error(sprintf('File "%s" already exists.', $filePath));
            return Command::FAILURE;
        }

        // 3. Generate Content
        $frontmatter = [
            'title' => $title,
            'date' => $date,
        ];

        if ($type) {
            $frontmatter['category'] = $type;
        }

        if ($isDraft) {
            $frontmatter['draft'] = true;
        }

        $fileContent = $this->buildFileContent($frontmatter, $title);

        // 4. Write File
        if (file_put_contents($filePath, $fileContent) === false) {
             $this->io->error(sprintf('Failed to write to file "%s".', $filePath));
             return Command::FAILURE;
        }

        $this->io->success(sprintf('Created new content file at %s', $filePath));

        return Command::SUCCESS;
    }

    private function slugify(string $text): string
    {
        // Convert to lowercase
        $slug = strtolower($text);
        // Replace non-letter or digits by -
        $slug = preg_replace('~[^\pL\d]+~u', '-', $slug);
        // Transliterate
        $slug = iconv('utf-8', 'us-ascii//TRANSLIT', $slug);
        // Remove unwanted characters
        $slug = preg_replace('~[^-\w]+~', '', $slug);
        // Trim
        $slug = trim($slug, '-');
        // Remove duplicate -
        $slug = preg_replace('~-+~', '-', $slug);

        if (empty($slug)) {
            return 'untitled';
        }

        return $slug;
    }

    private function buildFileContent(array $frontmatter, string $title): string
    {
        $yaml = "---\n";
        foreach ($frontmatter as $key => $value) {
            if (is_bool($value)) {
                $valStr = $value ? 'true' : 'false';
            } else {
                $valStr = is_string($value) ? '"' . str_replace('"', '\"', $value) . '"' : $value;
            }
            $yaml .= sprintf("%s: %s\n", $key, $valStr);
        }
        $yaml .= "---\n\n";
        
        $yaml .= sprintf("# %s\n\nWrite your content here...", $title);

        return $yaml;
    }
}
