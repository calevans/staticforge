<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Commands;

use EICC\StaticForge\Features\Deployment\Commands\UploadSiteCommand;
use EICC\StaticForge\Tests\Mocks\InMemorySftpClient;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * site:upload summary wording, exit code and manifest state when the delete guard trips or remote deletes fail.
 */
class UploadSiteCommandDeleteOutcomeTest extends UnitTestCase
{
    private const REMOTE = '/var/www/test';
    private const MANIFEST = '/var/www/test/staticforge-manifest.json';

    private string $workDir;
    private InMemorySftpClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workDir = sys_get_temp_dir() . '/staticforge_upload_outcome_test_' . uniqid();
        mkdir($this->workDir);
        mkdir($this->workDir . '/tmp');
        mkdir($this->workDir . '/output');
        mkdir($this->workDir . '/content');
        $this->client = new InMemorySftpClient();

        $this->setContainerVariable('TMP_DIR', $this->workDir . '/tmp');
        $this->setContainerVariable('OUTPUT_DIR', $this->workDir . '/output');
        $this->setContainerVariable('SOURCE_DIR', $this->workDir . '/content');
        $this->setContainerVariable('site_config', ['upload' => []]);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->workDir);
        parent::tearDown();
    }

    private function seedRemote(int $stale): void
    {
        file_put_contents($this->workDir . '/content/index.md', "---\ntitle: Home\n---\n# Home\n");
        $manifest = [];
        for ($i = 0; $i < $stale; $i++) {
            $manifest["old{$i}.html"] = "h{$i}";
            $this->client->files[self::REMOTE . "/old{$i}.html"] = 'x';
        }
        $this->client->files[self::MANIFEST] = (string) json_encode($manifest);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function runUpload(array $options = []): CommandTester
    {
        $tester = new CommandTester(new UploadSiteCommand($this->container, $this->client));
        $tester->execute(['--url' => 'https://example.test'] + $options, ['interactive' => false]);

        return $tester;
    }

    /**
     * @return array<string, mixed>
     */
    private function remoteManifest(): array
    {
        $decoded = json_decode($this->client->files[self::MANIFEST], true);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @return list<string>
     */
    private function deletedPaths(): array
    {
        $paths = array_map(
            static fn (string $call): string => substr($call, strlen('delete:')),
            $this->client->callsOf('delete')
        );
        sort($paths);

        return $paths;
    }

    public function testNonInteractiveGuardTripWithNoErrorsSaysDeletionsWereHeldBackAndFails(): void
    {
        $this->seedRemote(20);

        $tester = $this->runUpload();

        $display = $tester->getDisplay();
        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('deletions held back by the delete guard', $display);
        $this->assertStringNotContainsString(', 0 errors', $display);
    }

    public function testRealUploadErrorKeepsTheNormalSummaryWording(): void
    {
        $this->seedRemote(20);
        $this->client->failUploadNumber = 1;

        $tester = $this->runUpload();

        $display = $tester->getDisplay();
        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('1 errors', $display);
        $this->assertStringNotContainsString('held back', $display);
    }

    public function testFailedRemoteDeletesAreCountedAndFailTheCommand(): void
    {
        $this->seedRemote(3);
        $this->client->failAllDeletes = true;

        $tester = $this->runUpload();

        $display = $tester->getDisplay();
        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('3 remote deletion(s) failed', $display);
        $this->assertStringContainsString('Failed to delete: old0.html', $display);
    }

    public function testFailedDeletesStayInTheManifestWithOldHashesWhileUploadsAreRecordedAndSuccessesDropped(): void
    {
        $this->seedRemote(3);
        $this->client->failDeletePaths = [self::REMOTE . '/old0.html', self::REMOTE . '/old2.html'];

        $tester = $this->runUpload();

        $manifest = $this->remoteManifest();
        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('2 remote deletion(s) failed', $tester->getDisplay());
        $this->assertSame('h0', $manifest['old0.html']);
        $this->assertSame('h2', $manifest['old2.html']);
        $this->assertArrayNotHasKey('old1.html', $manifest);
        $this->assertArrayHasKey('robots.txt', $manifest);
        $this->assertNotSame([], $this->client->callsOf('upload'));
    }

    public function testFollowUpRunRetriesExactlyTheFailedPathsAndWritesAManifestWithoutThem(): void
    {
        $this->seedRemote(3);
        $this->client->failDeletePaths = [self::REMOTE . '/old0.html', self::REMOTE . '/old2.html'];
        $this->runUpload();
        $this->client->failDeletePaths = [];
        $this->setContainerVariable('OUTPUT_DIR', $this->workDir . '/output');
        $this->client->calls = [];

        $tester = $this->runUpload();

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame([self::REMOTE . '/old0.html', self::REMOTE . '/old2.html'], $this->deletedPaths());
        $manifest = $this->remoteManifest();
        $this->assertArrayNotHasKey('old0.html', $manifest);
        $this->assertArrayNotHasKey('old2.html', $manifest);
        $this->assertArrayHasKey('robots.txt', $manifest);
        $this->assertStringNotContainsString('remote deletion(s) failed', $tester->getDisplay());
    }
}
