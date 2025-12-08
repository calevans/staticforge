<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Commands;

use EICC\StaticForge\Commands\ListFeaturesCommand;
use EICC\StaticForge\Core\FeatureManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ListFeaturesCommandTest extends TestCase
{
    public function testExecuteListsFeatures(): void
    {
        // Mock FeatureManager
        $featureManager = $this->createMock(FeatureManager::class);

        // Configure mock to return some statuses
        $featureManager->method('getFeatureStatuses')
            ->willReturn([
                'EnabledFeature' => 'enabled',
                'DisabledFeature' => 'disabled'
            ]);

        // Expect loadFeatures to be called
        // Note: In the command we check if features are empty before calling loadFeatures.
        // Since we are mocking getFeatures() to return empty by default (unless configured),
        // we should configure getFeatures too if we want to test that logic,
        // or just expect loadFeatures to be called if getFeatures returns empty.

        $featureManager->method('getFeatures')
            ->willReturn([]);

        $featureManager->expects($this->once())
            ->method('loadFeatures');

        $application = new Application();
        $application->add(new ListFeaturesCommand($featureManager));
        $command = $application->find('system:features');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();

        // Check for table headers
        $this->assertStringContainsString('Feature Name', $output);
        $this->assertStringContainsString('Status', $output);

        // Check for feature names and statuses
        $this->assertStringContainsString('EnabledFeature', $output);
        $this->assertStringContainsString('Enabled', $output);
        $this->assertStringContainsString('DisabledFeature', $output);
        $this->assertStringContainsString('Disabled', $output);
    }

    public function testExecuteWithNoFeatures(): void
    {
        $featureManager = $this->createMock(FeatureManager::class);
        $featureManager->method('getFeatureStatuses')
            ->willReturn([]);

        $application = new Application();
        $application->add(new ListFeaturesCommand($featureManager));
        $command = $application->find('system:features');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('No features found', $output);
    }
}
