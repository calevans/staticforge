<?php

declare(strict_types=1);

namespace EICC\StaticForge\Commands\Make;

use EICC\StaticForge\Services\Slugger;
use EICC\Utils\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'make:content',
    description: 'Create a new content file with frontmatter'
)]
class ContentCreatorCommand extends Command
{
    protected Container $container;
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

        $titleArg = $input->getArgument('title');
        $title = is_string($titleArg) ? $titleArg : (string)$titleArg;

        $typeOption = $input->getOption('type');
        $type = is_string($typeOption) ? $typeOption : '';

        $dateOption = $input->getOption('date');
        $date = is_string($dateOption) ? $dateOption : (string)$dateOption;

        $isDraft = (bool)$input->getOption('draft');

        // --type becomes a directory under the source dir, so it must not climb out of it
        if ($type !== '' && !$this->isSafeTypePath($type)) {
            $this->io->error(sprintf('Invalid --type "%s": use a relative path inside the content directory.', $type));
            return Command::FAILURE;
        }

        // 1. Determine Directory
        $sourceDir = $this->container->getVariable('SOURCE_DIR');
        $baseDir = is_string($sourceDir) && $sourceDir !== '' ? rtrim($sourceDir, '/') : 'content';
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

    private function isSafeTypePath(string $type): bool
    {
        if (str_contains($type, "\0") || str_contains($type, '\\') || str_starts_with($type, '/')) {
            return false;
        }

        foreach (explode('/', $type) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }

    private function slugify(string $text): string
    {
        return Slugger::filename($text);
    }

    /**
     * @param array<string, mixed> $frontmatter
     */
    private function buildFileContent(array $frontmatter, string $title): string
    {
        $yaml = "---\n";
        foreach ($frontmatter as $key => $value) {
            if (is_bool($value)) {
                $valStr = $value ? 'true' : 'false';
            } else {
                // A JSON string is a valid YAML double-quoted scalar, backslashes and all
                $valStr = is_string($value)
                    ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : $value;
            }
            $yaml .= sprintf("%s: %s\n", $key, $valStr);
        }
        $yaml .= "---\n\n";

        $yaml .= sprintf("# %s\n\nWrite your content here...", $title);

        return $yaml;
    }
}
