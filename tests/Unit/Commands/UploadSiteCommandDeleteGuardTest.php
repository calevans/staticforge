<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Commands;

use EICC\StaticForge\Features\Deployment\Commands\UploadSiteCommand;
use EICC\StaticForge\Tests\Mocks\InMemorySftpClient;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * site:upload with an injected in-memory client: upload.* validation and the delete guard exit code.
 */
class UploadSiteCommandDeleteGuardTest extends UnitTestCase
{
    private string $workDir;
    private InMemorySftpClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workDir = sys_get_temp_dir() . '/staticforge_upload_cmd_test_' . uniqid();
        mkdir($this->workDir);
        mkdir($this->workDir . '/tmp');
        mkdir($this->workDir . '/output');
        mkdir($this->workDir . '/content');
        $this->client = new InMemorySftpClient();

        $this->setContainerVariable('TMP_DIR', $this->workDir . '/tmp');
        $this->setContainerVariable('OUTPUT_DIR', $this->workDir . '/output');
        $this->setContainerVariable('SOURCE_DIR', $this->workDir . '/content');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->workDir);
        parent::tearDown();
    }

    /**
     * @param array<string, mixed>|string $upload
     */
    private function tester(array|string $upload): CommandTester
    {
        $this->setContainerVariable('site_config', ['upload' => $upload]);

        return new CommandTester(new UploadSiteCommand($this->container, $this->client));
    }

    public function testCommandAcceptsAnInjectedClientAndExposesTheDeleteOptions(): void
    {
        $command = new UploadSiteCommand($this->container, $this->client);

        $definition = $command->getDefinition();
        foreach (['no-delete', 'force-delete', 'dry-run', 'test'] as $option) {
            $this->assertTrue($definition->hasOption($option), $option);
            $this->assertFalse($definition->getOption($option)->acceptValue());
        }
    }

    /**
     * @return array<string, array{array<string, mixed>|string, string}>
     */
    public static function badUploadConfig(): array
    {
        return [
            'max_delete text' => [['max_delete' => 'abc'], 'upload.max_delete'],
            'max_delete negative' => [['max_delete' => -1], 'upload.max_delete'],
            'atomic strategy' => [['strategy' => 'atomic'], 'not available in this version'],
            'unknown strategy' => [['strategy' => 'x'], 'upload.strategy'],
            'not a mapping' => ['on', "'upload' in siteconfig.yaml must be a mapping"],
        ];
    }

    /**
     * @param array<string, mixed>|string $upload
     */
    #[DataProvider('badUploadConfig')]
    public function testBadUploadConfigAbortsBeforeAnyRenderOrRemoteCall(array|string $upload, string $message): void
    {
        $tester = $this->tester($upload);

        $status = $tester->execute(['--url' => 'https://example.test']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString($message, $tester->getDisplay());
        $this->assertSame([], $this->client->calls);
        $this->assertSame([], glob($this->workDir . '/tmp/*'));
        $this->assertStringNotContainsString('Re-rendering', $tester->getDisplay());
    }

    private const REMOTE = '/var/www/test';

    /**
     * Content that renders to a few files, and an old remote manifest with $stale entries no longer built.
     */
    private function seedRemote(int $stale): void
    {
        file_put_contents($this->workDir . '/content/index.md', "---\ntitle: Home\n---\n# Home\n");
        $manifest = [];
        for ($i = 0; $i < $stale; $i++) {
            $manifest["old{$i}.html"] = "h{$i}";
            $this->client->files[self::REMOTE . "/old{$i}.html"] = 'x';
        }
        $this->client->files[self::REMOTE . '/staticforge-manifest.json'] = (string) json_encode($manifest);
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $upload
     */
    private function runUpload(array $options = [], array $upload = [], bool $interactive = false): CommandTester
    {
        $tester = $this->tester($upload);
        $tester->execute(['--url' => 'https://example.test'] + $options, ['interactive' => $interactive]);

        return $tester;
    }

    /**
     * @return array<string, mixed>
     */
    private function remoteManifest(): array
    {
        $decoded = json_decode($this->client->files[self::REMOTE . '/staticforge-manifest.json'], true);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    public function testNonInteractiveGuardTripFailsAndNamesTheWaysForward(): void
    {
        $this->seedRemote(20);

        $tester = $this->runUpload();

        $display = $tester->getDisplay();
        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('--force-delete', $display);
        $this->assertStringContainsString('upload.max_delete', $display);
        $this->assertSame([], $this->client->callsOf('delete'));
        $this->assertNotSame([], $this->client->callsOf('upload'));
        $this->assertSame('h19', $this->remoteManifest()['old19.html']);
    }

    public function testForceDeleteOptionDeletesPastTheGuardAndSucceeds(): void
    {
        $this->seedRemote(20);

        $tester = $this->runUpload(['--force-delete' => true]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertCount(20, $this->client->callsOf('delete'));
        $this->assertArrayNotHasKey('old0.html', $this->remoteManifest());
    }

    public function testNoDeleteOptionSucceedsWithoutDeleting(): void
    {
        $this->seedRemote(20);

        $tester = $this->runUpload(['--no-delete' => true]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame([], $this->client->callsOf('delete'));
        $this->assertArrayHasKey('old0.html', $this->remoteManifest());
    }

    public function testRaisingMaxDeleteInSiteConfigLetsTheDeletionThrough(): void
    {
        $this->seedRemote(20);

        $tester = $this->runUpload([], ['max_delete' => 20]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertCount(20, $this->client->callsOf('delete'));
    }

    public function testMaxDeleteZeroInSiteConfigTripsOnASingleStaleFile(): void
    {
        $this->seedRemote(1);

        $tester = $this->runUpload([], ['max_delete' => 0]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertSame([], $this->client->callsOf('delete'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function dryRunFlags(): array
    {
        return [
            '--dry-run' => ['--dry-run'],
            '--test' => ['--test'],
        ];
    }

    #[DataProvider('dryRunFlags')]
    public function testDryRunReportsTheGuardWithoutPromptingDeletingOrWriting(string $flag): void
    {
        $this->seedRemote(20);
        $manifestBefore = $this->client->files[self::REMOTE . '/staticforge-manifest.json'];

        $tester = $this->runUpload([$flag => true]);

        $display = $tester->getDisplay();
        $this->assertSame(0, $tester->getStatusCode(), $display);
        $this->assertStringContainsString('20 stale files would be deleted (limit 10)', $display);
        $this->assertStringContainsString('guard would trip', $display);
        $this->assertSame([], $this->client->callsOf('delete'));
        $this->assertSame([], $this->client->callsOf('upload'));
        $this->assertSame([], $this->client->callsOf('put'));
        $this->assertSame($manifestBefore, $this->client->files[self::REMOTE . '/staticforge-manifest.json']);
    }

    public function testFailedUploadFailsTheCommandWithoutDeletingEvenWithForceDelete(): void
    {
        $this->seedRemote(3);
        $this->client->failUploadNumber = 1;

        $tester = $this->runUpload(['--force-delete' => true]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertSame([], $this->client->callsOf('delete'));
        $this->assertSame([], $this->client->callsOf('put'));
    }

    public function testConnectionFailureFailsBeforeAnyUpload(): void
    {
        $this->seedRemote(3);
        $this->client->connectResult = false;

        $tester = $this->runUpload();

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertSame([], $this->client->callsOf('upload'));
    }

    public function testValidUploadConfigProceedsPastValidation(): void
    {
        $tester = $this->tester(['max_delete' => 5, 'strategy' => 'in_place']);

        $tester->execute(['--url' => 'https://example.test', '--dry-run' => true]);

        $this->assertStringContainsString('Re-rendering site for production', $tester->getDisplay());
    }
}
