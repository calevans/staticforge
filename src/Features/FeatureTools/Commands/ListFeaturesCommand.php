<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\FeatureTools\Commands;

use EICC\StaticForge\Core\FeatureManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\Table;

class ListFeaturesCommand extends Command
{
    protected static $defaultName = 'feature:list';
    protected static $defaultDescription = 'List all available features and their status';

    private FeatureManager $featureManager;

    public function __construct(FeatureManager $featureManager)
    {
        parent::__construct();
        $this->featureManager = $featureManager;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('List all available features and their status (enabled/disabled)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Ensure features are loaded so we have the status
        // Note: FeatureManager might have already loaded features during bootstrap or previous commands,
        // but loadFeatures() is idempotent or checks internally.
        // Actually, FeatureManager::loadFeatures() clears and reloads if called?
        // Let's check FeatureManager::loadFeatures implementation.
        // It doesn't seem to check if already loaded.
        // But in bootstrap.php, FeatureManager is just instantiated, not loaded.
        // RenderSiteCommand calls loadFeatures().
        // So we should call it here to be sure.

        // However, if we call it, we might duplicate logs.
        // But we need the statuses.

        // Let's check if features are already loaded.
        if (empty($this->featureManager->getFeatures())) {
             $this->featureManager->loadFeatures();
        }

        $statuses = $this->featureManager->getFeatureStatuses();

        if (empty($statuses)) {
            $io->warning('No features found.');
            return Command::SUCCESS;
        }

        $rows = [];
        ksort($statuses); // Sort by feature name

        foreach ($statuses as $name => $status) {
            $statusCell = $status === 'enabled'
                ? '<info>Enabled</info>'
                : '<comment>Disabled</comment>';

            $rows[] = [$name, $statusCell];
        }

        $table = new Table($output);
        $table
            ->setHeaders(['Feature Name', 'Status'])
            ->setRows($rows);

        $table->render();

        return Command::SUCCESS;
    }
}
