<?php

declare(strict_types=1);

namespace EICC\StaticForge\Commands;

use EICC\StaticForge\Core\ConfigurableFeatureInterface;
use EICC\StaticForge\Core\FeatureManager;
use EICC\Utils\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CheckCommand extends Command
{
    protected static $defaultName = 'site:check';
    protected static $defaultDescription = 'Validate project configuration against feature requirements';

    protected Container $container;

    public function __construct(Container $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function configure(): void
    {
        $this->setDescription('Validate project configuration against feature requirements');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Configuration Validation');

        $featureManager = $this->container->get(FeatureManager::class);
        $features = $featureManager->getFeatures();
        $siteConfig = $this->container->getVariable('site_config') ?? [];

        $errors = [];
        $checkedFeatures = 0;

        foreach ($features as $feature) {
            if (!$feature instanceof ConfigurableFeatureInterface) {
                continue;
            }

            $checkedFeatures++;
            $featureName = $feature->getName();

            // Check siteconfig.yaml requirements
            foreach ($feature->getRequiredConfig() as $key) {
                if (!$this->hasConfigKey($siteConfig, $key)) {
                    $errors[] = [
                        'feature' => $featureName,
                        'type' => 'Config',
                        'key' => $key,
                        'message' => "Missing key in siteconfig.yaml: {$key}"
                    ];
                }
            }

            // Check .env requirements
            foreach ($feature->getRequiredEnv() as $key) {
                if (!isset($_ENV[$key]) && getenv($key) === false) {
                    $errors[] = [
                        'feature' => $featureName,
                        'type' => 'Env',
                        'key' => $key,
                        'message' => "Missing environment variable: {$key}"
                    ];
                }
            }
        }

        $totalFeatures = count($features);
        $failedFeaturesCount = count(array_unique(array_column($errors, 'feature')));
        $passedFeatures = $checkedFeatures - $failedFeaturesCount;

        $io->section('Validation Summary');
        $io->text([
            sprintf('Total Features Scanned:       %d', $totalFeatures),
            sprintf('Features Requiring Config:    %d', $checkedFeatures),
            sprintf('Features Passed Validation:   %d', $passedFeatures),
        ]);

        if ($failedFeaturesCount > 0) {
            $io->text(sprintf('<error>Features Failed Validation:   %d</error>', $failedFeaturesCount));
        }

        $io->newLine();

        if (empty($errors)) {
            $io->success("All checks passed!");
            return Command::SUCCESS;
        }

        $io->error("Found " . count($errors) . " configuration errors.");

        $tableRows = [];
        foreach ($errors as $error) {
            $tableRows[] = [$error['feature'], $error['type'], $error['key'], $error['message']];
        }

        $io->table(['Feature', 'Type', 'Key', 'Message'], $tableRows);

        return Command::FAILURE;
    }

    /**
     * Check if a dot-notation key exists in the config array
     */
    private function hasConfigKey(array $config, string $key): bool
    {
        $parts = explode('.', $key);
        $current = $config;

        foreach ($parts as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return false;
            }
            $current = $current[$part];
        }

        return true;
    }
}
