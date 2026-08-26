<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\FeatureTools\Commands;

use EICC\StaticForge\Features\FeatureTools\Models\MigrationResult;
use EICC\StaticForge\Features\FeatureTools\Services\FeatureMigrator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'feature:migrate',
    description: 'Convert a pre-3.0 Feature.php from the array-based event contract to typed events'
)]
class FeatureMigrateCommand extends Command
{
    private FeatureMigrator $migrator;

    public function __construct(?FeatureMigrator $migrator = null)
    {
        parent::__construct();
        $this->migrator = $migrator ?? new FeatureMigrator();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Convert a pre-3.0 Feature.php from the array-based event contract to typed events')
            ->addArgument(
                'path',
                InputArgument::OPTIONAL,
                'A feature name (resolved under src/Features/) or a path to any directory containing a ' .
                'Feature.php — including an external package checkout'
            )
            ->addOption('all', null, InputOption::VALUE_NONE, 'Migrate every feature under src/Features/')
            ->addOption('write', null, InputOption::VALUE_NONE, 'Apply changes (default is a dry-run report only)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $write = (bool) $input->getOption('write');
        $all = (bool) $input->getOption('all');
        $path = $input->getArgument('path');

        if (!$all && $path === null) {
            $io->error('Provide a feature name/path, or use --all to migrate every feature under src/Features/.');
            return Command::FAILURE;
        }

        $targets = $all ? $this->discoverFeatureFiles() : $this->resolveSingleTarget((string) $path, $io);
        if ($targets === null) {
            return Command::FAILURE;
        }

        if (empty($targets)) {
            $io->warning('No Feature.php files found.');
            return Command::SUCCESS;
        }

        $title = $write ? 'Migrating Features to 3.0' : 'Migrating Features to 3.0 (dry run — pass --write to apply)';
        $io->title($title);

        $migratedCount = 0;
        $alreadyCount = 0;
        $skippedCount = 0;

        foreach ($targets as $target) {
            $result = $this->migrator->migrateFile($target);

            if ($result->alreadyMigrated) {
                $alreadyCount++;
                $io->text("<comment>skip</comment>  {$target} (already migrated)");
                continue;
            }

            if ($result->skipped) {
                $skippedCount++;
                $io->text("<error>skip</error>  {$target}");
                $io->text("        {$result->skipReason}");
                continue;
            }

            if (!$result->changed()) {
                $alreadyCount++;
                $io->text("<comment>skip</comment>  {$target} (nothing to change)");
                continue;
            }

            $migratedCount++;
            $label = $write ? 'wrote' : 'would write';
            $io->text("<info>{$label}</info>  {$target} ({$result->listenersConverted} listener(s) converted)");
            $this->printWarnings($result, $io);

            if ($write) {
                file_put_contents($target, $result->migratedContent);
            }
        }

        $io->newLine();
        $io->text(
            "Migrated: {$migratedCount}   Already done: {$alreadyCount}   " .
            "Skipped (needs manual work): {$skippedCount}"
        );

        if (!$write && $migratedCount > 0) {
            $io->note(
                'This was a dry run — no files were changed. Re-run with --write to apply, then use ' .
                '`git diff` to review exactly what changed (a proper diff, not a guess).'
            );
        }

        if ($migratedCount > 0) {
            $io->note(
                'Automated conversion handles the mechanical part only. Review every TODO(feature:migrate) ' .
                'comment it left behind, run phpcbf on the changed files to clean up formatting, run your ' .
                'tests, and re-run a real site:render before trusting the result. See migrating-to-3-0.html ' .
                'for what it does and does not do.'
            );
        }

        return $skippedCount > 0 && $migratedCount === 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @return string[]|null
     */
    private function resolveSingleTarget(string $path, SymfonyStyle $io): ?array
    {
        $candidates = [
            $path,
            getcwd() . '/src/Features/' . $path,
        ];

        foreach ($candidates as $candidate) {
            $featureFile = rtrim($candidate, '/') . '/Feature.php';
            if (is_file($featureFile)) {
                return [$featureFile];
            }
        }

        $io->error("Could not find a Feature.php at '{$path}' or 'src/Features/{$path}'.");
        return null;
    }

    /**
     * @return string[]
     */
    private function discoverFeatureFiles(): array
    {
        $baseDir = getcwd() . '/src/Features';
        if (!is_dir($baseDir)) {
            return [];
        }

        $files = [];
        foreach (scandir($baseDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $featureFile = $baseDir . '/' . $entry . '/Feature.php';
            if (is_file($featureFile)) {
                $files[] = $featureFile;
            }
        }

        sort($files);
        return $files;
    }

    private function printWarnings(MigrationResult $result, SymfonyStyle $io): void
    {
        foreach ($result->warnings as $warning) {
            $io->text("        <comment>note:</comment> {$warning}");
        }
    }
}
