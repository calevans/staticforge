<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Characterization\Html;

/**
 * Golden-file comparison for the HTML characterization tests.
 *
 * Goldens live in golden/{group}/{case}.txt and are compared byte-for-byte. To regenerate them
 * (deliberately, then review the diff) run with UPDATE_GOLDEN=1 set INSIDE the container, because
 * `lando phpunit` does not forward environment variables:
 *     lando ssh -c "env UPDATE_GOLDEN=1 vendor/bin/phpunit tests/Unit/Characterization/Html"
 * Without UPDATE_GOLDEN=1 the tests only compare.
 */
trait GoldenAssertions
{
    private static function goldenDir(string $group): string
    {
        return __DIR__ . '/golden/' . $group;
    }

    private function assertMatchesGolden(string $group, string $case, string $actual): void
    {
        $dir = self::goldenDir($group);
        $path = $dir . '/' . $case . '.txt';

        if (getenv('UPDATE_GOLDEN') === '1') {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($path, $actual);
        }

        $this->assertFileExists($path, "golden missing for {$group}/{$case}; regenerate with UPDATE_GOLDEN=1");
        $this->assertSame(
            file_get_contents($path),
            $actual,
            "{$group}/{$case} output changed from the frozen 3.3.7 behavior"
        );
    }

    /**
     * @param list<string> $expectedCases
     */
    private function assertNoOrphanedGoldens(string $group, array $expectedCases): void
    {
        $found = [];
        foreach (glob(self::goldenDir($group) . '/*.txt') ?: [] as $file) {
            $found[] = basename($file, '.txt');
        }
        sort($found);
        sort($expectedCases);

        $this->assertSame($expectedCases, $found, "golden files for {$group} must match the corpus case names");
    }
}
