<?php

declare(strict_types=1);

namespace EICC\StaticForge\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class FeatureCreateCommand extends Command
{
    protected static $defaultName = 'feature:create';
    protected static $defaultDescription = 'Scaffold a new internal feature following the Gold Standard architecture';

    protected function configure(): void
    {
        $this
            ->setDescription('Scaffold a new internal feature following our standard architecture.')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the feature (PascalCase)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $featureName = $input->getArgument('name');

        // Validate Feature Name (PascalCase)
        if (!preg_match('/^[A-Z][a-zA-Z0-9]*$/', $featureName)) {
            $io->error('Invalid feature name. Must be PascalCase (e.g., MyFeature).');
            return Command::FAILURE;
        }

        $baseDir = getcwd() . '/src/Features/' . $featureName;
        $servicesDir = $baseDir . '/Services';

        // Check if exists
        if (is_dir($baseDir)) {
            $io->error("Feature '{$featureName}' already exists at {$baseDir}");
            return Command::FAILURE;
        }

        // Create Directories
        if (!mkdir($baseDir, 0755, true)) {
            $io->error("Failed to create directory: {$baseDir}");
            return Command::FAILURE;
        }
        if (!mkdir($servicesDir, 0755, true)) {
            $io->error("Failed to create directory: {$servicesDir}");
            return Command::FAILURE;
        }

        // Generate Feature.php
        $featureContent = $this->getFeatureTemplate($featureName);
        if (file_put_contents($baseDir . '/Feature.php', $featureContent) === false) {
            $io->error("Failed to write Feature.php");
            return Command::FAILURE;
        }

        // Generate Service.php
        $serviceContent = $this->getServiceTemplate($featureName);
        if (file_put_contents($servicesDir . '/' . $featureName . 'Service.php', $serviceContent) === false) {
            $io->error("Failed to write Service class");
            return Command::FAILURE;
        }

        $io->success([
            "Feature '{$featureName}' created successfully.",
            "Location: src/Features/{$featureName}",
            "Don't forget to implement your logic in Services/{$featureName}Service.php"
        ]);

        return Command::SUCCESS;
    }

    private function getFeatureTemplate(string $name): string
    {
        return <<<PHP
<?php

namespace EICC\StaticForge\Features\\{$name};

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\EventManager;
use EICC\Utils\Container;
use EICC\StaticForge\Features\\{$name}\Services\\{$name}Service;

class Feature extends BaseFeature implements FeatureInterface
{
    protected string \$name = '{$name}';
    private {$name}Service \$service;

    protected array \$eventListeners = [
        // Example: 'PRE_LOOP' => ['method' => 'handleEvent', 'priority' => 100]
    ];

    public function register(EventManager \$eventManager, Container \$container): void
    {
        parent::register(\$eventManager, \$container);

        \$logger = \$container->get('logger');

        // Initialize service with dependencies
        \$this->service = new {$name}Service(\$logger);
    }

    public function handleEvent(Container \$container, array \$data): array
    {
        return \$this->service->process(\$container, \$data);
    }
}
PHP;
    }

    private function getServiceTemplate(string $name): string
    {
        return <<<PHP
<?php

namespace EICC\StaticForge\Features\\{$name}\Services;

use EICC\Utils\Container;
use EICC\Utils\Log;

class {$name}Service
{
    private Log \$logger;

    public function __construct(Log \$logger)
    {
        \$this->logger = \$logger;
    }

    public function process(Container \$container, array \$data): array
    {
        \$this->logger->log('INFO', "{$name}Service processing...");

        // Implement your logic here

        return \$data;
    }
}
PHP;
    }
}
