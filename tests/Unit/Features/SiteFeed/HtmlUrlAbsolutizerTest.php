<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\SiteFeed;

use EICC\StaticForge\Features\SiteFeed\Services\HtmlUrlAbsolutizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HtmlUrlAbsolutizerTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string}>
     */
    public static function cases(): array
    {
        $b = 'https://example.com/';
        $s = 'https://example.com/sub/';

        return [
            'root href' => ['<a href="/x">', $b, '<a href="https://example.com/x">'],
            'root src' => ['<img src="/a.png">', $b, '<img src="https://example.com/a.png">'],
            'single quotes' => ["<a href='/x'>", $b, "<a href='https://example.com/x'>"],
            'sub path base' => ['<a href="/x">', $s, '<a href="https://example.com/sub/x">'],
            'sub path base without trailing slash' => ['<a href="/x">', 'https://example.com/sub', '<a href="https://example.com/sub/x">'],
            'srcset candidates' => [
                '<img srcset="/a.png 1x, /b.png 2x">',
                $s,
                '<img srcset="https://example.com/sub/a.png 1x, https://example.com/sub/b.png 2x">',
            ],
            'protocol relative untouched' => ['<a href="//cdn.test/x">', $b, '<a href="//cdn.test/x">'],
            'absolute untouched' => ['<a href="https://o.test/x">', $b, '<a href="https://o.test/x">'],
            'relative untouched' => ['<a href="x.html">', $b, '<a href="x.html">'],
            'anchor untouched' => ['<a href="#top">', $b, '<a href="#top">'],
            'dollar in base is literal' => ['<a href="/x">', 'https://e.com/$1/', '<a href="https://e.com/$1/x">'],
            'backslash in base is literal' => ['<a href="/x">', 'https://e.com/a\\b/', '<a href="https://e.com/a\\b/x">'],
        ];
    }

    #[DataProvider('cases')]
    public function testRootRelativeUrlsGetTheSiteOriginAndEverythingElseIsUntouched(
        string $html,
        string $base,
        string $expected
    ): void {
        $this->assertSame($expected, HtmlUrlAbsolutizer::absolutize($html, $base));
    }
}
