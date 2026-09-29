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

/**
 * Ordering, delete guard, --no-delete/--force-delete and carry-over behaviour of SiteUploader,
 * run against a real SiteUploader and an in-memory remote.
 */
class SiteUploaderDeleteGuardTest extends UnitTestCase
{
    private const REMOTE = '/remote';
    private const MANIFEST = '/remote/staticforge-manifest.json';

    private string $localDir;
    private InMemorySftpClient $client;
    private SiteUploader $uploader;
    private BufferedOutput $output;

    protected function setUp(): void
    {
        parent::setUp();
        $this->localDir = sys_get_temp_dir() . '/staticforge_guard_test_' . uniqid();
        mkdir($this->localDir);
        $this->client = new InMemorySftpClient();
        $this->output = new BufferedOutput();
        $logger = $this->container->get('logger');
        $this->assertInstanceOf(Log::class, $logger);
        $this->uploader = new SiteUploader($this->client, $logger, new UploadCheckService(), new EventManager());
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->localDir);
        parent::tearDown();
    }

    /**
     * Local dir gets $kept unchanged files plus new.txt. The remote holds an old manifest of
     * $manifestSize entries: the kept files, and $stale entries (with remote copies) that no longer exist locally.
     *
     * @return list<string> Stale relative paths
     */
    private function seed(int $manifestSize, int $stale): array
    {
        $manifest = [];
        for ($i = 0; $i < $manifestSize - $stale; $i++) {
            $this->writeLocal("keep{$i}.txt", "keep {$i}");
            $manifest["keep{$i}.txt"] = md5("keep {$i}");
        }
        $this->writeLocal('new.txt', 'brand new');

        $stalePaths = [];
        for ($i = 0; $i < $stale; $i++) {
            $path = "old{$i}.txt";
            $stalePaths[] = $path;
            $manifest[$path] = "oldhash{$i}";
            $this->client->files[self::REMOTE . '/' . $path] = "old {$i}";
        }

        $this->client->files[self::MANIFEST] = (string) json_encode($manifest);
        $this->client->calls = [];

        return $stalePaths;
    }

    private function writeLocal(string $name, string $content): void
    {
        file_put_contents($this->localDir . '/' . $name, $content);
    }

    private function doUpload(?UploadOptions $options = null, bool $dryRun = false): int
    {
        return $this->uploader->upload($this->localDir, self::REMOTE, $dryRun, $this->output, $options);
    }

    /**
     * @return array<string, mixed>
     */
    private function writtenManifest(): array
    {
        $decoded = json_decode($this->client->files[self::MANIFEST], true);
        $this->assertIsArray($decoded);

        ksort($decoded, SORT_NATURAL);

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
     * @param list<string> $stale
     * @return list<string>
     */
    private function remote(array $stale): array
    {
        return array_map(static fn (string $p): string => self::REMOTE . '/' . $p, $stale);
    }

    public function testFilesAreUploadedThenStaleFilesDeletedThenManifestWritten(): void
    {
        $stale = $this->seed(3, 2);

        $this->assertSame(0, $this->doUpload());

        $calls = $this->client->calls;
        $upload = array_search('upload:/remote/new.txt', $calls, true);
        $firstDelete = array_search('delete:' . $this->remote($stale)[0], $calls, true);
        $lastDelete = array_search('delete:' . $this->remote($stale)[1], $calls, true);
        $manifestWrite = array_search('put:' . self::MANIFEST, $calls, true);

        $this->assertIsInt($upload);
        $this->assertIsInt($firstDelete);
        $this->assertIsInt($lastDelete);
        $this->assertIsInt($manifestWrite);
        $this->assertLessThan($firstDelete, $upload);
        $this->assertLessThan($manifestWrite, $lastDelete);
    }

    /**
     * @return array<string, array{int, int, bool}>
     */
    public static function guardBoundaries(): array
    {
        return [
            'empty old manifest, nothing stale' => [0, 0, false],
            'one entry, one stale' => [1, 1, false],
            'size 40 at limit 10' => [40, 10, false],
            'size 40 over limit 10' => [40, 11, true],
            'size 41 at limit 11' => [41, 11, false],
            'size 41 over limit 11' => [41, 12, true],
            'size 100 at limit 25' => [100, 25, false],
            'size 100 over limit 25' => [100, 26, true],
        ];
    }

    #[DataProvider('guardBoundaries')]
    public function testDefaultGuardLimitIsTheLargerOfTenAndQuarterOfTheOldManifest(
        int $manifestSize,
        int $stale,
        bool $shouldTrip
    ): void {
        $stalePaths = $this->seed($manifestSize, $stale);

        $errors = $this->doUpload();

        $this->assertSame(0, $errors);
        if ($shouldTrip) {
            $this->assertSame([], $this->deletedPaths());
            $this->assertSame('non_interactive', $this->uploader->getDeleteGuardAbort());
            $this->assertSame(array_map('strval', $stalePaths), array_keys(array_intersect_key(
                $this->writtenManifest(),
                array_flip($stalePaths)
            )));
        } else {
            $this->assertSame($this->remote($stalePaths), $this->deletedPaths());
            $this->assertNull($this->uploader->getDeleteGuardAbort());
            $this->assertSame([], array_intersect_key($this->writtenManifest(), array_flip($stalePaths)));
        }
    }

    public function testTrippedGuardStillUploadsAndCarriesStaleEntriesWithTheirOldHashes(): void
    {
        $stale = $this->seed(40, 11);

        $this->doUpload();

        $this->assertContains('upload:/remote/new.txt', $this->client->calls);
        $written = $this->writtenManifest();
        $this->assertSame(md5('brand new'), $written['new.txt']);
        foreach ($stale as $i => $path) {
            $this->assertSame("oldhash{$i}", $written[$path]);
            $this->assertArrayHasKey(self::REMOTE . '/' . $path, $this->client->files);
        }
        $this->assertStringContainsString('--force-delete', $this->output->fetch());
    }

    public function testConfiguredMaxDeleteOfZeroTripsOnAnySingleStaleFile(): void
    {
        $this->seed(5, 1);

        $this->doUpload(new UploadOptions(maxDelete: 0));

        $this->assertSame([], $this->deletedPaths());
        $this->assertSame('non_interactive', $this->uploader->getDeleteGuardAbort());
    }

    public function testConfiguredMaxDeleteOfZeroAllowsRunWithNothingStale(): void
    {
        $this->seed(5, 0);

        $this->doUpload(new UploadOptions(maxDelete: 0));

        $this->assertNull($this->uploader->getDeleteGuardAbort());
        $this->assertSame([], $this->deletedPaths());
    }

    public function testConfiguredMaxDeleteLowerThanDefaultLimitStillApplies(): void
    {
        $this->seed(40, 3);

        $this->doUpload(new UploadOptions(maxDelete: 2));

        $this->assertSame([], $this->deletedPaths());
        $this->assertSame('non_interactive', $this->uploader->getDeleteGuardAbort());
    }

    public function testStaleCountExactlyAtConfiguredMaxDeletePasses(): void
    {
        $stale = $this->seed(40, 3);

        $this->doUpload(new UploadOptions(maxDelete: 3));

        $this->assertSame($this->remote($stale), $this->deletedPaths());
    }

    public function testLargeConfiguredMaxDeleteLetsAMassDeletionThrough(): void
    {
        $stale = $this->seed(100, 90);

        $this->doUpload(new UploadOptions(maxDelete: 1000));

        $this->assertCount(90, $this->deletedPaths());
        $this->assertSame($this->remote($stale), $this->deletedPaths());
        $this->assertNull($this->uploader->getDeleteGuardAbort());
    }

    public function testForceDeleteOverridesATrippedGuard(): void
    {
        $stale = $this->seed(40, 30);

        $this->doUpload(new UploadOptions(forceDelete: true));

        $this->assertSame($this->remote($stale), $this->deletedPaths());
        $this->assertNull($this->uploader->getDeleteGuardAbort());
        $this->assertSame([], array_intersect_key($this->writtenManifest(), array_flip($stale)));
    }

    public function testForceDeleteOverridesAZeroMaxDelete(): void
    {
        $stale = $this->seed(5, 2);

        $this->doUpload(new UploadOptions(forceDelete: true, maxDelete: 0));

        $this->assertSame($this->remote($stale), $this->deletedPaths());
    }

    public function testNoDeleteUploadsButNeverDeletesAndKeepsStaleEntriesWithOldHashes(): void
    {
        $stale = $this->seed(4, 2);

        $this->assertSame(0, $this->doUpload(new UploadOptions(noDelete: true)));

        $this->assertContains('upload:/remote/new.txt', $this->client->calls);
        $this->assertSame([], $this->client->callsOf('delete'));
        $this->assertNull($this->uploader->getDeleteGuardAbort());
        $this->assertSame(
            [
                'keep0.txt' => md5('keep 0'),
                'keep1.txt' => md5('keep 1'),
                'new.txt' => md5('brand new'),
                $stale[0] => 'oldhash0',
                $stale[1] => 'oldhash1',
            ],
            $this->writtenManifest()
        );
    }

    public function testFollowUpRunWithoutNoDeleteRemovesExactlyTheCarriedEntries(): void
    {
        $stale = $this->seed(4, 2);
        $this->doUpload(new UploadOptions(noDelete: true));
        $this->client->calls = [];

        $this->assertSame(0, $this->doUpload());

        $this->assertSame($this->remote($stale), $this->deletedPaths());
        $this->assertSame([], $this->client->callsOf('upload'));
        $this->assertSame(
            ['keep0.txt' => md5('keep 0'), 'keep1.txt' => md5('keep 1'), 'new.txt' => md5('brand new')],
            $this->writtenManifest()
        );
    }

    public function testNoDeleteTakesPrecedenceOverForceDelete(): void
    {
        $stale = $this->seed(40, 30);

        $this->doUpload(new UploadOptions(noDelete: true, forceDelete: true));

        $this->assertSame([], $this->client->callsOf('delete'));
        $this->assertSame($stale, array_values(array_intersect(array_keys($this->writtenManifest()), $stale)));
    }

    public function testConfirmedGuardDeletesAndDropsStaleEntriesFromManifest(): void
    {
        $stale = $this->seed(40, 20);
        $asked = [];
        $confirm = static function (int $count) use (&$asked): bool {
            $asked[] = $count;
            return true;
        };

        $this->doUpload(new UploadOptions(confirmDelete: $confirm));

        $this->assertSame([20], $asked);
        $this->assertSame($this->remote($stale), $this->deletedPaths());
        $this->assertSame([], array_intersect_key($this->writtenManifest(), array_flip($stale)));
        $this->assertNull($this->uploader->getDeleteGuardAbort());
    }

    public function testDeclinedGuardDeletesNothingAndCarriesStaleEntriesOver(): void
    {
        $stale = $this->seed(40, 20);

        $errors = $this->doUpload(new UploadOptions(confirmDelete: static fn (int $count): bool => false));

        $this->assertSame(0, $errors);
        $this->assertSame([], $this->client->callsOf('delete'));
        $this->assertSame('declined', $this->uploader->getDeleteGuardAbort());
        $this->assertSame($stale, array_values(array_intersect(array_keys($this->writtenManifest()), $stale)));
        $this->assertSame('oldhash0', $this->writtenManifest()[$stale[0]]);
    }

    public function testConfirmationIsNotAskedWhenUnderTheLimit(): void
    {
        $this->seed(40, 5);
        $asked = 0;
        $confirm = static function (int $count) use (&$asked): bool {
            $asked++;
            return false;
        };

        $this->doUpload(new UploadOptions(confirmDelete: $confirm));

        $this->assertSame(0, $asked);
        $this->assertCount(5, $this->deletedPaths());
    }

    public function testNonInteractiveTripReportsHowToProceedAndFlagsTheAbort(): void
    {
        $this->seed(40, 20);

        $this->doUpload();

        $display = $this->output->fetch();
        $this->assertSame('non_interactive', $this->uploader->getDeleteGuardAbort());
        $this->assertStringContainsString('--force-delete', $display);
        $this->assertStringContainsString('upload.max_delete', $display);
    }

    public function testDryRunReportsCountLimitAndWouldTripWithoutPromptingDeletingOrWriting(): void
    {
        $this->seed(40, 20);
        $asked = 0;
        $confirm = static function (int $count) use (&$asked): bool {
            $asked++;
            return true;
        };

        $this->doUpload(new UploadOptions(confirmDelete: $confirm), true);

        $display = $this->output->fetch();
        $this->assertSame(0, $asked);
        $this->assertStringContainsString('20 stale files would be deleted (limit 10)', $display);
        $this->assertStringContainsString('guard would trip', $display);
        $this->assertSame([], $this->client->callsOf('delete'));
        $this->assertSame([], $this->client->callsOf('upload'));
        $this->assertSame([], $this->client->callsOf('put'));
    }

    public function testDryRunUnderTheLimitDoesNotClaimTheGuardWouldTrip(): void
    {
        $this->seed(40, 5);

        $this->doUpload(null, true);

        $display = $this->output->fetch();
        $this->assertStringContainsString('5 stale files would be deleted (limit 10)', $display);
        $this->assertStringNotContainsString('guard would trip', $display);
        $this->assertSame([], $this->client->callsOf('delete'));
    }

    public function testDryRunWithForceDeleteDoesNotClaimTheGuardWouldTrip(): void
    {
        $this->seed(40, 20);

        $this->doUpload(new UploadOptions(forceDelete: true), true);

        $this->assertStringNotContainsString('guard would trip', $this->output->fetch());
        $this->assertSame([], $this->client->callsOf('delete'));
    }

    public function testFailedUploadMeansNoDeletionsAndNoManifestWrite(): void
    {
        $this->seed(4, 2);
        $oldManifest = $this->client->files[self::MANIFEST];
        $this->client->failUploadNumber = 1;

        $errors = $this->doUpload();

        $this->assertSame(1, $errors);
        $this->assertSame([], $this->client->callsOf('delete'));
        $this->assertSame([], $this->client->callsOf('put'));
        $this->assertSame($oldManifest, $this->client->files[self::MANIFEST]);
    }

    public function testFailedUploadBlocksDeletionsEvenWithForceDelete(): void
    {
        $this->seed(40, 30);
        $this->client->failUploadNumber = 1;

        $errors = $this->doUpload(new UploadOptions(forceDelete: true));

        $this->assertSame(1, $errors);
        $this->assertSame([], $this->client->callsOf('delete'));
        $this->assertSame([], $this->client->callsOf('put'));
    }

    public function testFailureOfALaterUploadAlsoBlocksDeletionsAndManifestWrite(): void
    {
        $this->seed(2, 1);
        $this->writeLocal('zzz-second.txt', 'second');
        $this->client->failUploadNumber = 2;

        $errors = $this->doUpload(new UploadOptions(forceDelete: true));

        $this->assertSame(1, $errors);
        $this->assertSame([], $this->client->callsOf('delete'));
        $this->assertSame([], $this->client->callsOf('put'));
    }

    public function testUnsafeManifestEntriesAreIgnoredAndNeverDeleted(): void
    {
        $this->writeLocal('new.txt', 'brand new');
        $manifest = [
            '../escape.txt' => 'h',
            '/etc/passwd' => 'h',
            'a/./b.txt' => 'h',
            'a//b.txt' => 'h',
            './x.txt' => 'h',
            'sub/../y.txt' => 'h',
            '' => 'h',
            'legit-stale.txt' => 'h',
        ];
        $this->client->files[self::MANIFEST] = (string) json_encode($manifest);
        $this->client->files[self::REMOTE . '/legit-stale.txt'] = 'x';

        $this->doUpload(new UploadOptions(forceDelete: true));

        $this->assertSame([self::REMOTE . '/legit-stale.txt'], $this->deletedPaths());
        $this->assertSame(['new.txt'], array_keys($this->writtenManifest()));
    }

    public function testUnsafeEntriesDoNotInflateTheStaleCountForTheGuard(): void
    {
        $this->writeLocal('new.txt', 'brand new');
        $manifest = ['legit-stale.txt' => 'h'];
        for ($i = 0; $i < 20; $i++) {
            $manifest["../evil{$i}.txt"] = 'h';
        }
        $this->client->files[self::MANIFEST] = (string) json_encode($manifest);
        $this->client->files[self::REMOTE . '/legit-stale.txt'] = 'x';

        $this->doUpload(new UploadOptions(maxDelete: 1));

        $this->assertSame([self::REMOTE . '/legit-stale.txt'], $this->deletedPaths());
        $this->assertNull($this->uploader->getDeleteGuardAbort());
    }

    public function testManifestAndHtaccessEntriesAreNeverCountedOrDeleted(): void
    {
        $this->writeLocal('new.txt', 'brand new');
        $this->client->files[self::MANIFEST] = (string) json_encode([
            'staticforge-manifest.json' => 'h',
            '.htaccess' => 'h',
        ]);
        $this->client->files[self::REMOTE . '/.htaccess'] = 'Options -Indexes';

        $this->doUpload(new UploadOptions(maxDelete: 0));

        $this->assertSame([], $this->client->callsOf('delete'));
        $this->assertNull($this->uploader->getDeleteGuardAbort());
        $this->assertSame('Options -Indexes', explode("\n", $this->client->files[self::REMOTE . '/.htaccess'])[0]);
        $this->assertSame(['new.txt'], array_keys($this->writtenManifest()));
    }

    public function testFailedRemoteDeleteDoesNotAbortTheRunOrTheManifestWrite(): void
    {
        $this->seed(3, 1);
        $this->client->failAllDeletes = true;

        $errors = $this->doUpload();

        $this->assertSame(1, $errors);
        $this->assertSame(1, $this->uploader->getDeleteFailureCount());
        $this->assertArrayHasKey('old0.txt', $this->writtenManifest());
        $this->assertSame('oldhash0', $this->writtenManifest()['old0.txt']);
        $this->assertContains('put:' . self::MANIFEST, $this->client->calls);
        $this->assertStringContainsString('Failed to delete', $this->output->fetch());
    }

    public function testGuardAbortStateResetsOnTheNextRun(): void
    {
        $this->seed(40, 20);
        $this->doUpload();
        $this->assertSame('non_interactive', $this->uploader->getDeleteGuardAbort());

        $this->doUpload(new UploadOptions(forceDelete: true));

        $this->assertNull($this->uploader->getDeleteGuardAbort());
    }
}
