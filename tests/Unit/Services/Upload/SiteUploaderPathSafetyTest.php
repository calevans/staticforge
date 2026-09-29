<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Services\Upload;

use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Services\Upload\SiteUploader;
use EICC\StaticForge\Services\Upload\UploadCheckService;
use EICC\StaticForge\Services\Upload\UploadOptions;
use EICC\StaticForge\Tests\Mocks\InMemorySftpClient;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\Utils\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Unsafe manifest paths, console escaping, summary wording and failed-delete handling of SiteUploader.
 */
class SiteUploaderPathSafetyTest extends UnitTestCase
{
    private const REMOTE = '/remote';
    private const MANIFEST = '/remote/staticforge-manifest.json';

    private string $localDir;
    private InMemorySftpClient $client;
    private SiteUploader $uploader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->localDir = sys_get_temp_dir() . '/staticforge_pathsafety_test_' . uniqid();
        mkdir($this->localDir);
        $this->client = new InMemorySftpClient();
        $logger = $this->container->get('logger');
        $this->assertInstanceOf(Log::class, $logger);
        $this->uploader = new SiteUploader($this->client, $logger, new UploadCheckService(), new EventManager());
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->localDir);
        parent::tearDown();
    }

    private function writeLocal(string $name, string $content): void
    {
        file_put_contents($this->localDir . '/' . $name, $content);
    }

    /**
     * @param array<string, string> $manifest
     * @param list<string> $remoteFiles Relative paths that exist remotely
     */
    private function seedRemote(array $manifest, array $remoteFiles = []): void
    {
        $this->client->files[self::MANIFEST] = (string) json_encode($manifest);
        foreach ($remoteFiles as $path) {
            $this->client->files[self::REMOTE . '/' . $path] = 'remote';
        }
        $this->client->calls = [];
    }

    private function decorated(): BufferedOutput
    {
        return new BufferedOutput(OutputInterface::VERBOSITY_VERBOSE, true);
    }

    private function doUpload(BufferedOutput $output, ?UploadOptions $options = null, bool $dryRun = false): int
    {
        return $this->uploader->upload($this->localDir, self::REMOTE, $dryRun, $output, $options);
    }

    /**
     * @return array<string, mixed>
     */
    private function writtenManifest(): array
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
        return array_map(
            static fn (string $call): string => substr($call, strlen('delete:')),
            $this->client->callsOf('delete')
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function controlCharacterPaths(): array
    {
        return [
            'NUL' => ["bad\x00.txt"],
            'SOH' => ["bad\x01.txt"],
            'ESC' => ["bad\x1b.txt"],
            'CR' => ["bad\r.txt"],
            'LF' => ["bad\n.txt"],
            'DEL' => ["bad\x7f.txt"],
            'TAB' => ["bad\t.txt"],
        ];
    }

    #[DataProvider('controlCharacterPaths')]
    public function testManifestPathWithControlCharacterIsNeverDeletedNorCarriedOverAndIsReportedUnsafe(
        string $badPath
    ): void {
        $this->writeLocal('new.txt', 'brand new');
        $this->seedRemote([$badPath => 'h', 'stale.txt' => 'h'], ['stale.txt', $badPath]);
        $output = new BufferedOutput();

        $this->doUpload($output, new UploadOptions(forceDelete: true));

        $this->assertSame([self::REMOTE . '/stale.txt'], $this->deletedPaths());
        $this->assertArrayNotHasKey($badPath, $this->writtenManifest());
        $this->assertSame(['new.txt'], array_keys($this->writtenManifest()));
        $this->assertArrayHasKey(self::REMOTE . '/' . $badPath, $this->client->files);
        $this->assertStringContainsString('Ignoring unsafe manifest entry: bad', $output->fetch());
    }

    #[DataProvider('controlCharacterPaths')]
    public function testUnsafeControlCharacterEntryDoesNotCountTowardsTheGuard(string $badPath): void
    {
        $this->writeLocal('new.txt', 'brand new');
        $this->seedRemote([$badPath => 'h', 'stale.txt' => 'h'], ['stale.txt']);

        $this->doUpload(new BufferedOutput(), new UploadOptions(maxDelete: 1));

        $this->assertNull($this->uploader->getDeleteGuardAbort());
        $this->assertSame([self::REMOTE . '/stale.txt'], $this->deletedPaths());
    }

    public function testPathsWithSpacesAndUnicodeAreStillUploadedAndDeleted(): void
    {
        $this->writeLocal('my file é.txt', 'local');
        $this->seedRemote(
            ['old dir/日本 語.txt' => 'h', 'ünï cödé.txt' => 'h'],
            ['old dir/日本 語.txt', 'ünï cödé.txt']
        );

        $errors = $this->doUpload(new BufferedOutput());

        $this->assertSame(0, $errors);
        $this->assertSame(
            [self::REMOTE . '/old dir/日本 語.txt', self::REMOTE . '/ünï cödé.txt'],
            $this->deletedPaths()
        );
        $this->assertContains('upload:/remote/my file é.txt', $this->client->calls);
        $this->assertSame(['my file é.txt'], array_keys($this->writtenManifest()));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function formatterTagPaths(): array
    {
        return [
            'closing then opening tag' => ['</error><info>x</info>'],
            'inline style tag' => ['<fg=red>x</>'],
        ];
    }

    #[DataProvider('formatterTagPaths')]
    public function testUnsafeEntryContainingFormatterTagsIsPrintedLiterally(string $tags): void
    {
        $this->writeLocal('new.txt', 'brand new');
        $this->seedRemote([$tags . "\x01" => 'h']);
        $output = $this->decorated();

        $this->doUpload($output);

        $display = $output->fetch();
        $this->assertStringContainsString('Ignoring unsafe manifest entry: ' . $tags . '?', $display);
    }

    #[DataProvider('formatterTagPaths')]
    public function testDryRunWouldDeleteLineContainingFormatterTagsIsPrintedLiterally(string $tags): void
    {
        $this->writeLocal('new.txt', 'brand new');
        $this->seedRemote([$tags => 'h'], [$tags]);
        $output = $this->decorated();

        $this->doUpload($output, null, true);

        $display = $output->fetch();
        $this->assertStringContainsString('Would delete: ' . $tags, $display);
        $this->assertSame([], $this->client->callsOf('delete'));
    }

    #[DataProvider('formatterTagPaths')]
    public function testDeletedLineContainingFormatterTagsIsPrintedLiterally(string $tags): void
    {
        $this->writeLocal('new.txt', 'brand new');
        $this->seedRemote([$tags => 'h'], [$tags]);
        $output = $this->decorated();

        $this->doUpload($output);

        $display = $output->fetch();
        $this->assertStringContainsString('Deleted: ' . $tags, $display);
        $this->assertStringContainsString("\e[", $display, 'decoration must be active for this test to mean anything');
    }

    #[DataProvider('formatterTagPaths')]
    public function testFailedDeleteLineContainingFormatterTagsIsPrintedLiterallyInBothPlaces(string $tags): void
    {
        $this->writeLocal('new.txt', 'brand new');
        $this->seedRemote([$tags => 'h'], [$tags]);
        $this->client->failAllDeletes = true;
        $output = $this->decorated();

        $this->doUpload($output);

        $display = $output->fetch();
        $this->assertSame(2, substr_count($display, 'Failed to delete: ' . $tags));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function localFilenamesWithTags(): array
    {
        return [
            'opening error tag' => ['<error>x<info>.txt'],
            'inline style tag' => ['<fg=red>bold.txt'],
        ];
    }

    #[DataProvider('localFilenamesWithTags')]
    public function testFailedUploadLineContainingFormatterTagsInLocalFilenameIsPrintedLiterally(string $name): void
    {
        $this->writeLocal($name, 'content');
        $this->client->failUploadNumber = 1;
        $output = $this->decorated();

        $errors = $this->doUpload($output);

        $this->assertSame(1, $errors);
        $this->assertSame(2, substr_count($output->fetch(), 'Failed to upload: ' . $name));
    }

    #[DataProvider('localFilenamesWithTags')]
    public function testDryRunWouldUploadLineContainingFormatterTagsIsPrintedLiterally(string $name): void
    {
        $this->writeLocal($name, 'content');
        $output = $this->decorated();

        $this->doUpload($output, null, true);

        $this->assertStringContainsString('Would upload: ' . $name, $output->fetch());
    }

    public function testControlCharactersInDisplayedRemotePathAreReplacedByQuestionMarks(): void
    {
        $this->writeLocal('new.txt', 'brand new');
        $this->seedRemote(["a\e[2Jb\r\n.txt" => 'h']);
        $output = $this->decorated();

        $this->doUpload($output);

        $display = $output->fetch();
        $this->assertStringContainsString('a?[2Jb??.txt', $display);
        $this->assertStringNotContainsString("\e[2J", $display);
    }

    public function testControlCharactersInDisplayedLocalFilenameAreReplacedByQuestionMarks(): void
    {
        $this->writeLocal("a\x1bb\nc.txt", 'content');
        $this->client->failUploadNumber = 1;
        $output = $this->decorated();

        $this->doUpload($output);

        $display = $output->fetch();
        $this->assertStringContainsString('Failed to upload: a?b?c.txt', $display);
        $this->assertStringNotContainsString("a\x1bb", $display);
    }

    public function testNonInteractiveGuardTripWithNoErrorsSaysDeletionsWereHeldBack(): void
    {
        $this->writeLocal('new.txt', 'brand new');
        $manifest = [];
        for ($i = 0; $i < 20; $i++) {
            $manifest["old{$i}.txt"] = 'h';
        }
        $this->seedRemote($manifest);
        $output = new BufferedOutput();

        $errors = $this->doUpload($output);

        $display = $output->fetch();
        $this->assertSame(0, $errors);
        $this->assertStringContainsString('deletions held back by the delete guard', $display);
        $this->assertStringNotContainsString('0 errors', $display);
    }

    public function testDeclinedGuardKeepsThePlainZeroErrorsSummary(): void
    {
        $this->writeLocal('new.txt', 'brand new');
        $manifest = [];
        for ($i = 0; $i < 20; $i++) {
            $manifest["old{$i}.txt"] = 'h';
        }
        $this->seedRemote($manifest);
        $output = new BufferedOutput();

        $this->doUpload($output, new UploadOptions(confirmDelete: static fn (int $count): bool => false));

        $this->assertStringContainsString('1 files uploaded, 0 errors', $output->fetch());
    }

    public function testRealUploadErrorKeepsTheNormalSummaryWording(): void
    {
        $this->writeLocal('new.txt', 'brand new');
        $manifest = [];
        for ($i = 0; $i < 20; $i++) {
            $manifest["old{$i}.txt"] = 'h';
        }
        $this->seedRemote($manifest);
        $this->client->failUploadNumber = 1;
        $output = new BufferedOutput();

        $errors = $this->doUpload($output);

        $display = $output->fetch();
        $this->assertSame(1, $errors);
        $this->assertStringContainsString('0 files uploaded, 1 errors', $display);
        $this->assertStringNotContainsString('held back', $display);
    }

    public function testMixOfFailedAndSuccessfulDeletesKeepsOnlyFailedEntriesWithOldHashes(): void
    {
        $this->writeLocal('new.txt', 'brand new');
        $this->seedRemote(
            ['a.txt' => 'hashA', 'b.txt' => 'hashB', 'c.txt' => 'hashC', 'd.txt' => 'hashD'],
            ['a.txt', 'b.txt', 'c.txt', 'd.txt']
        );
        $this->client->failDeletePaths = [self::REMOTE . '/b.txt', self::REMOTE . '/d.txt'];
        $output = new BufferedOutput();

        $errors = $this->doUpload($output);

        $this->assertSame(2, $errors);
        $this->assertSame(2, $this->uploader->getDeleteFailureCount());
        $written = $this->writtenManifest();
        ksort($written);
        $this->assertSame(
            ['b.txt' => 'hashB', 'd.txt' => 'hashD', 'new.txt' => md5('brand new')],
            $written
        );
        $this->assertStringContainsString('Errors occurred during upload', $output->fetch());
    }

    public function testFollowUpRunRetriesExactlyTheFailedPathsAndThenDropsThem(): void
    {
        $this->writeLocal('new.txt', 'brand new');
        $this->seedRemote(
            ['a.txt' => 'hashA', 'b.txt' => 'hashB', 'c.txt' => 'hashC'],
            ['a.txt', 'b.txt', 'c.txt']
        );
        $this->client->failDeletePaths = [self::REMOTE . '/b.txt'];
        $this->doUpload(new BufferedOutput());
        $this->assertSame(1, $this->uploader->getDeleteFailureCount());
        $this->client->failDeletePaths = [];
        $this->client->calls = [];

        $errors = $this->doUpload(new BufferedOutput());

        $this->assertSame(0, $errors);
        $this->assertSame(0, $this->uploader->getDeleteFailureCount());
        $this->assertSame([self::REMOTE . '/b.txt'], $this->deletedPaths());
        $this->assertSame(['new.txt' => md5('brand new')], $this->writtenManifest());
    }

    public function testDeleteFailureCountIsZeroWhenNothingFailsAndResetsBetweenRuns(): void
    {
        $this->writeLocal('new.txt', 'brand new');
        $this->seedRemote(['a.txt' => 'h'], ['a.txt']);
        $this->client->failAllDeletes = true;
        $this->doUpload(new BufferedOutput());
        $this->assertSame(1, $this->uploader->getDeleteFailureCount());

        $this->client->failAllDeletes = false;
        $this->doUpload(new BufferedOutput());

        $this->assertSame(0, $this->uploader->getDeleteFailureCount());
    }
}
