<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Commands;

use EICC\StaticForge\Commands\Audit\ConfigCommand;
use EICC\StaticForge\Services\Upload\UploadSettings;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * audit:config validation of the `upload:` block.
 */
class ConfigCommandUploadTest extends UnitTestCase
{
    private string $testDir;
    private string $originalCwd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCwd = (string) getcwd();
        $this->testDir = sys_get_temp_dir() . '/staticforge_config_upload_test_' . uniqid();
        mkdir($this->testDir);
        mkdir($this->testDir . '/content');
        mkdir($this->testDir . '/templates');
        touch($this->testDir . '/.env');
        chdir($this->testDir);
        $this->setContainerVariable('TEMPLATE', 'sample');
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        $this->removeDirectory($this->testDir);
        parent::tearDown();
    }

    /**
     * @param mixed $upload Value for `upload:`; null omits the key
     */
    private function audit(mixed $upload): CommandTester
    {
        $config = ['site' => ['name' => 'Test Site']];
        if ($upload !== null) {
            $config['upload'] = $upload;
        }
        $this->setContainerVariable('site_config', $config);

        $application = new Application();
        $application->addCommand(new ConfigCommand($this->container));
        $tester = new CommandTester($application->find('audit:config'));
        $tester->execute([]);

        return $tester;
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function validBlocks(): array
    {
        return [
            'key absent' => [null],
            'max_delete zero' => [['max_delete' => 0]],
            'max_delete null' => [['max_delete' => null]],
            'in_place' => [['max_delete' => 5, 'strategy' => 'in_place']],
            'unknown key' => [['keep_releases' => 3]],
        ];
    }

    #[DataProvider('validBlocks')]
    public function testValidOrAbsentUploadBlockIsQuiet(mixed $upload): void
    {
        $tester = $this->audit($upload);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringNotContainsString('Upload', $tester->getDisplay());
        $this->assertStringContainsString('Audit passed', $tester->getDisplay());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidBlocks(): array
    {
        return [
            'max_delete string' => [['max_delete' => 'abc']],
            'max_delete quoted' => [['max_delete' => '5']],
            'max_delete negative' => [['max_delete' => -1]],
            'max_delete float' => [['max_delete' => 2.5]],
            'max_delete bool' => [['max_delete' => true]],
            'max_delete list' => [['max_delete' => []]],
            'atomic' => [['strategy' => 'atomic']],
            'unknown strategy' => [['strategy' => 'x']],
            'both wrong' => [['max_delete' => -3, 'strategy' => 'atomic']],
            'upload is a scalar' => ['on'],
        ];
    }

    #[DataProvider('invalidBlocks')]
    public function testInvalidUploadBlockIsReportedWithTheSameMessagesAsUploadSettings(mixed $upload): void
    {
        $tester = $this->audit($upload);

        $display = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Upload', $display);
        $messages = UploadSettings::fromConfig($upload)->errors;
        $this->assertNotSame([], $messages);
        foreach ($messages as $message) {
            $this->assertStringContainsString($message, $display);
        }
    }
}
