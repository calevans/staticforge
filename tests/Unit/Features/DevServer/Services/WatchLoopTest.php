<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\DevServer\Services;

use EICC\StaticForge\Features\DevServer\Services\BuildRequest;
use EICC\StaticForge\Features\DevServer\Services\WatchLoop;
use EICC\StaticForge\Tests\Mocks\FakeClock;
use PHPUnit\Framework\TestCase;

class WatchLoopTest extends TestCase
{
    private FakeClock $clock;
    private WatchLoop $loop;

    protected function setUp(): void
    {
        $this->clock = new FakeClock(10000);
        $this->loop = new WatchLoop($this->clock);
    }

    /**
     * @param array<string, string> $signature
     */
    private function tick(array $signature, bool $building = false, int $advance = 0): ?BuildRequest
    {
        $this->clock->advance($advance);

        return $this->loop->tick($signature, $building);
    }

    public function testFirstTickOnlyRecordsBaselineAndNeverBuilds(): void
    {
        $this->assertNull($this->tick(['/a' => '1:1']));
        $this->assertNull($this->tick(['/a' => '1:1'], false, 5000));
    }

    public function testUnchangedSignatureNeverBuilds(): void
    {
        $this->tick(['/a' => '1:1']);

        for ($i = 0; $i < 5; $i++) {
            $this->assertNull($this->tick(['/a' => '1:1'], false, 1000));
        }
    }

    public function testChangeWaitsForDebounceThenRequestsOneNonCleanBuild(): void
    {
        $this->tick(['/a' => '1:1']);

        $this->assertNull($this->tick(['/a' => '2:1'], false, 100));
        $this->assertNull($this->tick(['/a' => '2:1'], false, 299));
        $request = $this->tick(['/a' => '2:1'], false, 1);

        $this->assertNotNull($request);
        $this->assertFalse($request->clean);
        $this->assertSame(['/a'], $request->changed);
        $this->assertSame([], $request->deleted);
    }

    public function testBurstInsideDebounceWindowProducesExactlyOneBuild(): void
    {
        $this->tick(['/a' => '1:1', '/b' => '1:1']);

        $this->assertNull($this->tick(['/a' => '2:1', '/b' => '1:1'], false, 100));
        $this->assertNull($this->tick(['/a' => '2:1', '/b' => '2:1'], false, 100));
        $this->assertNull($this->tick(['/a' => '3:1', '/b' => '2:1'], false, 100));
        $request = $this->tick(['/a' => '3:1', '/b' => '2:1'], false, 300);

        $this->assertNotNull($request);
        $this->assertEqualsCanonicalizing(['/a', '/b'], $request->changed);
        $this->assertNull($this->tick(['/a' => '3:1', '/b' => '2:1'], false, 5000));
    }

    public function testEachChangeRestartsTheDebounceWindow(): void
    {
        $this->tick(['/a' => '1:1']);
        $this->tick(['/a' => '2:1'], false, 200);

        $this->assertNull($this->tick(['/a' => '3:1'], false, 250));
        $this->assertNull($this->tick(['/a' => '3:1'], false, 299));
        $this->assertNotNull($this->tick(['/a' => '3:1'], false, 1));
    }

    public function testNoBuildIsRequestedWhileOneIsRunning(): void
    {
        $this->tick(['/a' => '1:1']);
        $this->tick(['/a' => '2:1'], true, 1000);

        $this->assertNull($this->tick(['/a' => '2:1'], true, 5000));
    }

    public function testChangesDuringABuildQueueExactlyOneFollowUp(): void
    {
        $this->tick(['/a' => '1:1', '/b' => '1:1']);
        $this->tick(['/a' => '2:1', '/b' => '1:1'], false, 400);
        $first = $this->tick(['/a' => '2:1', '/b' => '1:1'], false, 400);
        $this->assertNotNull($first);
        $this->assertSame(['/a'], $first->changed);

        // Build is now running; edits keep arriving.
        $this->assertNull($this->tick(['/a' => '2:1', '/b' => '2:1'], true, 400));
        $this->assertNull($this->tick(['/a' => '3:1', '/b' => '2:1'], true, 400));
        $this->assertNull($this->tick(['/a' => '3:1', '/b' => '3:1'], true, 400));

        $followUp = $this->tick(['/a' => '3:1', '/b' => '3:1'], false, 400);
        $this->assertNotNull($followUp);
        $this->assertEqualsCanonicalizing(['/a', '/b'], $followUp->changed);

        $this->assertNull($this->tick(['/a' => '3:1', '/b' => '3:1'], false, 5000));
    }

    public function testChangeBeforeBuildFinishesIsNotLostWhenBuildEnds(): void
    {
        $this->tick(['/a' => '1:1']);
        $this->tick(['/a' => '2:1'], false, 400);

        $this->tick(['/a' => '3:1'], true, 400);
        $request = $this->tick(['/a' => '3:1'], false, 400);

        $this->assertNotNull($request);
        $this->assertSame(['/a'], $request->changed);
    }

    public function testDeletedSourceForcesCleanBuild(): void
    {
        $this->tick(['/a' => '1:1', '/b' => '1:1']);

        $this->tick(['/a' => '1:1'], false, 100);
        $request = $this->tick(['/a' => '1:1'], false, 300);

        $this->assertNotNull($request);
        $this->assertTrue($request->clean);
        $this->assertSame(['/b'], $request->deleted);
        $this->assertSame([], $request->changed);
    }

    public function testRenameForcesCleanBuildAndReportsBothPaths(): void
    {
        $this->tick(['/old.md' => '1:1']);

        $this->tick(['/new.md' => '1:1'], false, 100);
        $request = $this->tick(['/new.md' => '1:1'], false, 300);

        $this->assertNotNull($request);
        $this->assertTrue($request->clean);
        $this->assertSame(['/old.md'], $request->deleted);
        $this->assertSame(['/new.md'], $request->changed);
    }

    public function testCleanFlagDoesNotStickAfterTheCleanBuildWasRequested(): void
    {
        $this->tick(['/a' => '1:1', '/b' => '1:1']);
        $this->tick(['/a' => '1:1'], false, 100);
        $clean = $this->tick(['/a' => '1:1'], false, 300);
        $this->assertNotNull($clean);
        $this->assertTrue($clean->clean);
        $this->loop->buildFinished(true);

        $this->tick(['/a' => '2:1'], false, 100);
        $next = $this->tick(['/a' => '2:1'], false, 300);

        $this->assertNotNull($next);
        $this->assertFalse($next->clean);
        $this->assertSame([], $next->deleted);
    }

    public function testCleanStaysPendingAfterFailedBuildWithoutAutoRetry(): void
    {
        $this->tick(['/a' => '1:1', '/b' => '1:1']);
        $this->tick(['/a' => '1:1'], false, 100);
        $first = $this->tick(['/a' => '1:1'], false, 300);
        $this->assertNotNull($first);
        $this->loop->buildFinished(false);

        $this->assertNull($this->tick(['/a' => '1:1'], false, 1000));

        $this->tick(['/a' => '2:1'], false, 100);
        $next = $this->tick(['/a' => '2:1'], false, 300);
        $this->assertNotNull($next);
        $this->assertTrue($next->clean);
        $this->assertSame(['/b'], $next->deleted);
    }

    public function testDeleteDuringABuildMakesTheFollowUpClean(): void
    {
        $this->tick(['/a' => '1:1', '/b' => '1:1']);
        $this->tick(['/a' => '2:1', '/b' => '1:1'], false, 400);

        $this->tick(['/a' => '2:1'], true, 400);
        $followUp = $this->tick(['/a' => '2:1'], false, 400);

        $this->assertNotNull($followUp);
        $this->assertTrue($followUp->clean);
    }

    public function testFileChangedThenDeletedIsReportedOnlyAsDeleted(): void
    {
        $this->tick(['/a' => '1:1', '/b' => '1:1']);
        $this->tick(['/a' => '1:1', '/b' => '2:1'], false, 100);
        $this->tick(['/a' => '1:1'], false, 100);
        $request = $this->tick(['/a' => '1:1'], false, 300);

        $this->assertNotNull($request);
        $this->assertSame([], $request->changed);
        $this->assertSame(['/b'], $request->deleted);
    }

    public function testSameSizeMtimeChangeIsDetected(): void
    {
        $this->tick(['/a' => '1000:42']);
        $this->tick(['/a' => '1001:42'], false, 100);

        $request = $this->tick(['/a' => '1001:42'], false, 300);

        $this->assertNotNull($request);
        $this->assertSame(['/a'], $request->changed);
    }

    public function testSameMtimeSizeChangeIsDetected(): void
    {
        $this->tick(['/a' => '1000:42']);
        $this->tick(['/a' => '1000:43'], false, 100);

        $this->assertNotNull($this->tick(['/a' => '1000:43'], false, 300));
    }

    public function testNewFileIsReportedAsChangedNotDeleted(): void
    {
        $this->tick([]);
        $this->tick(['/new' => '1:1'], false, 100);
        $request = $this->tick(['/new' => '1:1'], false, 300);

        $this->assertNotNull($request);
        $this->assertFalse($request->clean);
        $this->assertSame(['/new'], $request->changed);
    }

    public function testCustomDebounceIsHonoured(): void
    {
        $loop = new WatchLoop($this->clock, 1000);
        $loop->tick(['/a' => '1:1'], false);
        $this->clock->advance(10);
        $loop->tick(['/a' => '2:1'], false);

        $this->clock->advance(999);
        $this->assertNull($loop->tick(['/a' => '2:1'], false));
        $this->clock->advance(1);
        $this->assertNotNull($loop->tick(['/a' => '2:1'], false));
    }

    public function testCleanRequirementSurvivesRepeatedFailuresAndClearsOnlyAfterASuccess(): void
    {
        $this->tick(['/a' => '1:1', '/b' => '1:1']);
        $this->tick(['/a' => '1:1'], false, 100);
        $this->assertTrue($this->tick(['/a' => '1:1'], false, 300)?->clean);
        $this->loop->buildFinished(false);

        $this->tick(['/a' => '2:1'], false, 100);
        $second = $this->tick(['/a' => '2:1'], false, 300);
        $this->assertNotNull($second);
        $this->assertTrue($second->clean);
        $this->assertSame(['/b'], $second->deleted);
        $this->assertSame(['/a'], $second->changed);
        $this->loop->buildFinished(false);

        $this->tick(['/a' => '3:1'], false, 100);
        $third = $this->tick(['/a' => '3:1'], false, 300);
        $this->assertNotNull($third);
        $this->assertTrue($third->clean);
        $this->loop->buildFinished(true);

        $this->tick(['/a' => '4:1'], false, 100);
        $fourth = $this->tick(['/a' => '4:1'], false, 300);
        $this->assertNotNull($fourth);
        $this->assertFalse($fourth->clean);
        $this->assertSame([], $fourth->deleted);
    }

    public function testFailedBuildIsNeverRetriedWithoutANewChange(): void
    {
        $this->tick(['/a' => '1:1']);
        $this->tick(['/a' => '2:1'], false, 100);
        $this->assertNotNull($this->tick(['/a' => '2:1'], false, 300));
        $this->loop->buildFinished(false);

        for ($i = 0; $i < 5; $i++) {
            $this->assertNull($this->tick(['/a' => '2:1'], false, 10000));
        }
    }

    public function testNewDeletionAfterFailedCleanBuildIsMergedWithTheCarriedOne(): void
    {
        $this->tick(['/a' => '1:1', '/b' => '1:1', '/c' => '1:1']);
        $this->tick(['/a' => '1:1', '/c' => '1:1'], false, 100);
        $this->assertNotNull($this->tick(['/a' => '1:1', '/c' => '1:1'], false, 300));
        $this->loop->buildFinished(false);

        $this->tick(['/a' => '1:1'], false, 100);
        $next = $this->tick(['/a' => '1:1'], false, 300);

        $this->assertNotNull($next);
        $this->assertTrue($next->clean);
        $this->assertEqualsCanonicalizing(['/b', '/c'], $next->deleted);
    }

    public function testBuildFinishedWithoutAnyPendingCleanIsHarmless(): void
    {
        $this->tick(['/a' => '1:1']);
        $this->loop->buildFinished(false);
        $this->loop->buildFinished(true);

        $this->tick(['/a' => '2:1'], false, 100);
        $request = $this->tick(['/a' => '2:1'], false, 300);

        $this->assertNotNull($request);
        $this->assertFalse($request->clean);
    }
}
