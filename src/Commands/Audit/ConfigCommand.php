<?php

declare(strict_types=1);

namespace EICC\StaticForge\Commands\Audit;

use EICC\StaticForge\Core\ConfigurableFeatureInterface;
use EICC\StaticForge\Core\FeatureManager;
use EICC\Utils\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ConfigCommand extends Command
{
    protected static $defaultName = 'audit:config';
    protected static $defaultDescription = 'Validate project configuration (audit:config)';

    protected Container $container;

    public function __construct(Container $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function configure(): void
    {
        $this->setDescription('Validate project configuration, environment, and features');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Configuration Audit');

        $siteConfig = $this->container->getVariable('site_config') ?? [];
        $errors = [];

        // --- 1. Core Configuration Checks ---
        $io->section('Core Configuration');

        // Check for Base URL (Expected in ENV usually)
        if (empty($_ENV['SITE_BASE_URL']) && empty(getenv('SITE_BASE_URL'))) {
             $errors[] = [
                'scope' => 'Core',
                'type' => 'Missing Config',
                'message' => "Missing 'SITE_BASE_URL' in environment (.env).",
            ];
        }

        // Check for Template/Theme (Expected in siteconfig or ENV)
        $template = $this->container->getVariable('TEMPLATE');
        if (empty($template)) {
             $errors[] = [
                'scope' => 'Core',
                'type' => 'Missing Config',
                'message' => "Missing 'site.template' in siteconfig.yaml or 'TEMPLATE' in .env.",
            ];
        }

        // Check for Site Name (Best practice)
        if (!$this->hasConfigKey($siteConfig, 'site.name')) {
             $errors[] = [
                'scope' => 'Core',
                'type' => 'Missing Config',
                'message' => "Missing 'site.name' in siteconfig.yaml.",
            ];
        }

        // Validate Paths (if they exist in config, or verify defaults exist)
        // Assuming default structure if not configured: content/, templates/, public/
        // If config overrides them, we check those.
        // For now, let's check standard paths relative to project root.

        $projectRoot = getcwd();
        $pathsToCheck = [
            'content/' => 'Content Directory',
            'templates/' => 'Templates Directory',
            // 'public/' => 'Output Directory (will be created if missing, but good to check permissions if it exists)'
        ];

        foreach ($pathsToCheck as $path => $label) {
            $fullPath = $projectRoot . '/' . $path;
            if (!is_dir($fullPath)) {
                 $errors[] = [
                    'scope' => 'Filesystem',
                    'type' => 'Missing Directory',
                    'message' => "{$label} not found at: {$path}",
                ];
            }
        }

        // --- 2. Environment Checks ---
        $io->section('Environment');
        if (!file_exists($projectRoot . '/.env')) {
             $errors[] = [
                'scope' => 'Environment',
                'type' => 'Missing File',
                'message' => ".env file not found in project root.",
            ];
        } else {
            $io->text("Found .env file.");
        }

        // --- 3. Feature Configuration Checks ---
        $io->section('Feature Configuration');

        $featureManager = $this->container->get(FeatureManager::class);
        $features = $featureManager->getFeatures();

        $checkedFeatures = 0;
        $featureErrors = 0;

        foreach ($features as $feature) {
            if (!$feature instanceof ConfigurableFeatureInterface) {
                continue;
            }

            $checkedFeatures++;
            $featureName = $feature->getName();
            $thisFeatureHasError = false;

            // Check siteconfig.yaml requirements
            foreach ($feature->getRequiredConfig() as $key) {
                if (!$this->hasConfigKey($siteConfig, $key)) {
                    $message = "Missing key in siteconfig.yaml: {$key}";

                    if (method_exists($feature, 'getConfigHelp')) {
                        $help = $feature->getConfigHelp($key);
                        if ($help) {
                            $message .= "\n<comment>Example Configuration:</comment>\n" . $help;
                        }
                    }

                    $errors[] = [
                        'scope' => "Feature: {$featureName}",
                        'type' => 'Config',
                        'message' => $message
                    ];
                    $thisFeatureHasError = true;
                }
            }

            // Check .env requirements
            foreach ($feature->getRequiredEnv() as $key) {
                // Check $_ENV, $_SERVER, and getenv()
                if (!isset($_ENV[$key]) && getenv($key) === false && !isset($_SERVER[$key])) {
                    $errors[] = [
                        'scope' => "Feature: {$featureName}",
                        'type' => 'Env',
                        'message' => "Missing environment variable: {$key}"
                    ];
                    $thisFeatureHasError = true;
                }
            }

            if ($thisFeatureHasError) {
                $featureErrors++;
            }
        }

        $io->text(sprintf('Scanned %d configurable features.', $checkedFeatures));

        // --- Summary ---
        $io->section('Audit Summary');

        if (empty($errors)) {
            $io->success("Audit passed! No configuration errors found.");
            return Command::SUCCESS;
        }

        $io->error(sprintf("Found %d configuration errors.", count($errors)));

        $groupedErrors = [];
        foreach ($errors as $error) {
            $groupedErrors[$error['scope']][] = $error;
        }
        ksort($groupedErrors);

        foreach ($groupedErrors as $scope => $scopeErrors) {
            $io->writeln("<fg=cyan;options=bold>{$scope}</>");
            foreach ($scopeErrors as $error) {
                // Config command doesn't seem to distinct error/warning in the array, using [ERROR]
                // but we can check if 'type' is useful.
                $typeLabel = strtoupper($error['type']);
                $io->writeln("  <fg=red>[{$typeLabel}]</> {$error['message']}");
            }
            $io->writeln("");
        }

        return Command::FAILURE;
    }

    private function hasConfigKey(array $config, string $key): bool
    {
        $keys = explode('.', $key);
        $current = $config;

        foreach ($keys as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return false;
            }
            $current = $current[$segment];
        }

        return true;
    }
}
