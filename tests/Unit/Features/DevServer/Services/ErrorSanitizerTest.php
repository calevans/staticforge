<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\DevServer\Services;

use EICC\StaticForge\Features\DevServer\Services\ErrorSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ErrorSanitizerTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function appRootProvider(): array
    {
        return [
            'child path' => ['/app', 'Error in /app/x.php', 'Error in ./x.php'],
            'root itself' => ['/app', 'cwd /app', 'cwd .'],
            'root followed by colon' => ['/app', '/app:12', '.:12'],
            'root followed by slash and end' => ['/app', 'in /app/', 'in ./'],
            'sibling sharing the prefix' => ['/app', 'Error in /apple/x.php', 'Error in /apple/x.php'],
            'sibling with dot suffix' => ['/app', 'see /app.bak/x', 'see /app.bak/x'],
            'sibling with dash suffix' => ['/app', 'see /app-old/x', 'see /app-old/x'],
            'sibling with underscore suffix' => ['/app', 'see /app_old/x', 'see /app_old/x'],
            'trailing slash on root' => ['/app/', 'Error in /app/x.php', 'Error in ./x.php'],
            'several trailing slashes' => ['/app///', 'Error in /app/x.php', 'Error in ./x.php'],
            'root appearing twice' => ['/app', '/app/a and /app/b', './a and ./b'],
            'nested root' => ['/srv/site', 'x /srv/site/src/y.php', 'x ./src/y.php'],
            'regex metacharacters in root' => ['/srv/a+b(1)', 'x /srv/a+b(1)/y', 'x ./y'],
        ];
    }

    #[DataProvider('appRootProvider')]
    public function testAppRootIsMaskedOnPathBoundariesOnly(string $appRoot, string $error, string $expected): void
    {
        $this->assertSame($expected, ErrorSanitizer::sanitize($error, $appRoot, '', '/nonexistent-tmp'));
    }

    public function testHomeDirectoryIsMaskedAsTilde(): void
    {
        $result = ErrorSanitizer::sanitize('open /home/cal/.ssh/id_rsa failed', '/app', '/home/cal', '/tmp');

        $this->assertSame('open ~/.ssh/id_rsa failed', $result);
    }

    public function testHomeDirectorySiblingIsNotMasked(): void
    {
        $result = ErrorSanitizer::sanitize('open /home/calvin/x', '/app', '/home/cal', '/tmp');

        $this->assertSame('open /home/calvin/x', $result);
    }

    public function testTempDirectoryIsMaskedAsTmpPlaceholder(): void
    {
        $result = ErrorSanitizer::sanitize('cannot write /var/tmp/sf/cache.json', '/app', '/home/cal', '/var/tmp');

        $this->assertSame('cannot write <tmp>/sf/cache.json', $result);
    }

    public function testTempDirectoryWithTrailingSlashIsMasked(): void
    {
        $result = ErrorSanitizer::sanitize('cannot write /tmp/x', '/app', '/home/cal', '/tmp/');

        $this->assertSame('cannot write <tmp>/x', $result);
    }

    public function testTheLongestMatchingRootWinsWhenRootsNest(): void
    {
        $result = ErrorSanitizer::sanitize(
            'a /tmp/site/x b /tmp/other/y c /home/cal/z',
            '/tmp/site',
            '/home/cal',
            '/tmp'
        );

        $this->assertSame('a ./x b <tmp>/other/y c ~/z', $result);
    }

    public function testEmptyErrorYieldsEmptyString(): void
    {
        $this->assertSame('', ErrorSanitizer::sanitize('', '/app', '/home/cal', '/tmp'));
        $this->assertSame('', ErrorSanitizer::sanitize("  \n ", '/app', '/home/cal', '/tmp'));
    }

    public function testEmptyAndRootPathsAreNeverUsedAsMasks(): void
    {
        $error = 'open /etc/passwd and /var/log/x';

        $this->assertSame($error, ErrorSanitizer::sanitize($error, '', '', ''));
        $this->assertSame($error, ErrorSanitizer::sanitize($error, '/', '/', '/'));
    }

    public function testMissingHomeFallsBackToTheEnvironmentWithoutFailing(): void
    {
        $this->assertSame('plain text', ErrorSanitizer::sanitize('plain text', '/app'));
    }

    public function testOutputIsCappedAtFiveLines(): void
    {
        $lines = array_map(static fn(int $i): string => "line {$i}", range(1, 9));

        $result = ErrorSanitizer::sanitize(implode("\n", $lines), '/app', '/home/cal', '/tmp');

        $this->assertSame("line 1\nline 2\nline 3\nline 4\nline 5", $result);
    }

    public function testOutputIsCappedAtFiveHundredCharactersCountingCharactersNotBytes(): void
    {
        $result = ErrorSanitizer::sanitize(str_repeat("\u{00e9}", 900), '/app', '/home/cal', '/tmp');

        $this->assertSame(500, mb_strlen($result));
        $this->assertTrue(mb_check_encoding($result, 'UTF-8'));
    }

    public function testMaskingHappensBeforeTheCharacterCap(): void
    {
        $error = str_repeat('/srv/very/long/project/root/file.php ', 20);

        $result = ErrorSanitizer::sanitize($error, '/srv/very/long/project/root', '/home/cal', '/tmp');

        $this->assertStringStartsWith('./file.php ./file.php', $result);
        $this->assertStringNotContainsString('/srv/very', $result);
    }

    public function testInvalidUtf8IsReplacedSoTheResultIsAlwaysValidUtf8(): void
    {
        $result = ErrorSanitizer::sanitize("bad \xff\xfe bytes in /app/x", '/app', '/home/cal', '/tmp');

        $this->assertTrue(mb_check_encoding($result, 'UTF-8'));
        $this->assertStringContainsString('bytes in ./x', $result);
    }

    public function testWindowsStyleLineEndingsAreSplitIntoLines(): void
    {
        $result = ErrorSanitizer::sanitize("a\r\nb\r\nc\r\nd\r\ne\r\nf", '/app', '/home/cal', '/tmp');

        $this->assertSame("a\nb\nc\nd\ne", $result);
    }
}
