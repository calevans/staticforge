<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\FeatureTools\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'feature:create',
    description: 'Scaffold a new internal feature following the Gold Standard architecture'
)]
class FeatureCreateCommand extends Command
{
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

declare(strict_types=1);

namespace EICC\StaticForge\Features\\{$name};

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\Events\Event;
use EICC\StaticForge\Core\Events\EventListener;
use EICC\StaticForge\Features\\{$name}\Services\\{$name}Service;

/**
 * Feature entry point for {$name}.
 */
class Feature extends BaseFeature implements FeatureInterface
{
    protected string \$name = '{$name}';
    private {$name}Service \$service;

    public function __construct({$name}Service \$service)
    {
        \$this->service = \$service;
    }

    /**
     * TODO: change 'PRE_LOOP' to the event you actually need, and swap
     * Event for a typed event (e.g. RenderEvent) if it carries per-file
     * data. #[EventListener] attributes are auto-discovered by
     * BaseFeature — no need to override register() just to wire this up.
     */
    #[EventListener('PRE_LOOP', priority: 100)]
    public function handleEvent(Event \$event): void
    {
        \$this->service->process();
    }
}
PHP;
    }

    private function getServiceTemplate(string $name): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\\{$name}\Services;

use EICC\Utils\Log;

/**
 * Service class for {$name} feature logic.
 */
class {$name}Service
{
    private Log \$logger;

    public function __construct(Log \$logger)
    {
        \$this->logger = \$logger;
    }

    /**
     * Process logic for this feature.
     */
    public function process(): void
    {
        \$this->logger->log('INFO', "{$name}Service processing...");

        // Implement your logic here
    }
}
PHP;
    }
}
