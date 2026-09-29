<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\SiteFeed;

use EICC\StaticForge\Features\SiteFeed\Services\FeedText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FeedTextTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function xmlCases(): array
    {
        return [
            'plain text is untouched' => ['Hello, world', 'Hello, world'],
            'tab, LF and CR are legal XML and stay' => ["a\tb\nc\rd", "a\tb\nc\rd"],
            'C0 controls are removed' => ["a\x00\x01\x08\x0B\x0C\x0E\x1Fb", 'ab'],
            'CDATA terminator is plain text' => ['a ]]> b', 'a ]]> b'],
            'astral emoji stay' => ['🎉', '🎉'],
            'U+FFFE and U+FFFF are removed' => ["a\u{FFFE}\u{FFFF}b", 'ab'],
        ];
    }

    #[DataProvider('xmlCases')]
    public function testXmlRemovesCharactersXml10ForbidsAndRepairsInvalidUtf8(string $input, string $expected): void
    {
        $this->assertSame($expected, FeedText::xml($input));
    }

    public function testXmlRepairsInvalidUtf8WithoutLosingSurroundingText(): void
    {
        $result = FeedText::xml("a\xFFb\xC3\x28c");

        $this->assertTrue(mb_check_encoding($result, 'UTF-8'));
        $this->assertMatchesRegularExpression('/^a.*b.*c$/su', $result);
    }

    public function testScrubReplacesInvalidUtf8AndLeavesValidTextAlone(): void
    {
        $this->assertSame('café 🎉', FeedText::scrub('café 🎉'));
        $this->assertTrue(mb_check_encoding(FeedText::scrub("bad \xC3\x28 \xE2\x82"), 'UTF-8'));
    }

    public function testScrubKeepsControlCharsBecauseJsonCanEscapeThem(): void
    {
        $this->assertSame("a\x01b", FeedText::scrub("a\x01b"));
    }
}
