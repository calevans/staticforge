<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\DevServer\Services;

use EICC\StaticForge\Features\DevServer\Services\ProcessBuildRunner;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

/**
 * Runs the real ProcessBuildRunner against a stub bin/staticforge.php in a temp app root,
 * so no actual site build ever happens.
 */
class ProcessBuildRunnerTest extends UnitTestCase
{
    use SymlinkSafeCleanup;

    private const LIMIT = 65536;

    private string $app;
    private ?ProcessBuildRunner $runner = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = (string) realpath(sys_get_temp_dir()) . '/sf_runner_' . bin2hex(random_bytes(4));
        mkdir($this->app . '/bin', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->runner?->stop();
        $this->removeDirectory($this->app);
        parent::tearDown();
    }

    private function stub(string $body): void
    {
        file_put_contents($this->app . '/bin/staticforge.php', "<?php\n" . $body);
    }

    private function runBuild(bool $clean = false, bool $drafts = false): ProcessBuildRunner
    {
        $this->runner = new ProcessBuildRunner($this->app, $drafts);
        $this->runner->start($clean);
        $deadline = microtime(true) + 30;
        while ($this->runner->isRunning()) {
            $this->assertLessThan($deadline, microtime(true), 'stub build did not finish');
            usleep(20000);
        }

        return $this->runner;
    }

    public function testKeepsTheTailOfOutputBeyondTheLimitWhereTheErrorIs(): void
    {
        $this->stub(<<<'PHP'
fwrite(STDOUT, 'HEADMARK' . str_repeat('x', 200000) . "\n");
usleep(300000);
fwrite(STDOUT, str_repeat('y', 30000) . "\n");
usleep(300000);
fwrite(STDOUT, "Fatal error: TAILMARK\n");
exit(1);
PHP);

        $runner = $this->runBuild();

        $this->assertSame(1, $runner->exitCode());
        $this->assertLessThanOrEqual(self::LIMIT, strlen($runner->output()));
        $this->assertStringContainsString('TAILMARK', $runner->output());
        $this->assertStringNotContainsString('HEADMARK', $runner->output());
        $this->assertSame('Fatal error: TAILMARK', trim(substr($runner->output(), -23)));
    }

    public function testOutputIsBoundedEvenWhenTheBuildWritesFarMoreThanTheLimit(): void
    {
        $this->stub(<<<'PHP'
for ($i = 0; $i < 40; $i++) {
    fwrite(STDOUT, str_repeat((string) ($i % 10), 50000) . "\n");
}
fwrite(STDERR, "Exception: TAILERR\n");
exit(2);
PHP);

        $runner = $this->runBuild();

        $this->assertSame(2, $runner->exitCode());
        $this->assertLessThanOrEqual(self::LIMIT, strlen($runner->output()));
        $this->assertStringContainsString('TAILERR', $runner->output());
    }

    public function testShortOutputIsKeptWholeAndExitCodeIsReported(): void
    {
        $this->stub('echo "one\ntwo\n"; fwrite(STDERR, "three\n"); exit(0);');

        $runner = $this->runBuild();

        $this->assertSame(0, $runner->exitCode());
        $this->assertStringContainsString("one\ntwo\n", $runner->output());
        $this->assertStringContainsString('three', $runner->output());
    }

    public function testOutputIsResetWhenANewBuildStarts(): void
    {
        $this->stub('echo "MARK-" . ($argv[3] ?? "none") . "\n"; exit(0);');
        $first = $this->runBuild();
        $this->assertStringContainsString('MARK-', $first->output());
        $this->stub('echo "SECOND\n"; exit(0);');

        $runner = $this->runBuild();

        $this->assertStringContainsString('SECOND', $runner->output());
        $this->assertStringNotContainsString('MARK-', $runner->output());
    }

    public function testBuildIsInvokedWithIncrementalNoAnsiAndTheRequestedFlags(): void
    {
        $this->stub('echo json_encode(array_slice($argv, 1));');

        $plain = json_decode($this->runBuild()->output(), true);
        $clean = json_decode($this->runBuild(true, true)->output(), true);

        $this->assertSame(['site:render', '--incremental', '--no-ansi'], $plain);
        $this->assertSame(['site:render', '--incremental', '--clean', '--include-drafts', '--no-ansi'], $clean);
    }

    public function testExitCodeIsNullWhileRunning(): void
    {
        $this->stub('usleep(1500000);');
        $this->runner = new ProcessBuildRunner($this->app);

        $this->runner->start(false);

        $this->assertNull($this->runner->exitCode());
        $this->assertTrue($this->runner->isRunning());
    }

    public function testStartingWhileABuildIsRunningIsRejected(): void
    {
        $this->stub('usleep(1500000);');
        $this->runner = new ProcessBuildRunner($this->app);
        $this->runner->start(false);

        $this->expectException(\LogicException::class);

        $this->runner->start(false);
    }

    public function testStopTerminatesARunningBuildAndAllowsANewOne(): void
    {
        $this->stub('usleep(30000000);');
        $this->runner = new ProcessBuildRunner($this->app);
        $this->runner->start(false);

        $this->runner->stop();

        $this->assertFalse($this->runner->isRunning());
        $this->stub('echo "again"; exit(0);');
        $runner = $this->runBuild();
        $this->assertStringContainsString('again', $runner->output());
    }

    public function testStopWithoutARunningBuildIsHarmless(): void
    {
        (new ProcessBuildRunner($this->app))->stop();

        $this->expectNotToPerformAssertions();
    }

    public function testIsRunningIsFalseBeforeAnyBuild(): void
    {
        $this->assertFalse((new ProcessBuildRunner($this->app))->isRunning());
    }
}
