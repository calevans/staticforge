<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\SiteFeed;

use EICC\StaticForge\Features\SiteFeed\Services\JsonFeedBuilder;
use PHPUnit\Framework\TestCase;

class JsonFeedBuilderTest extends TestCase
{
    use ItemFactory;

    /**
     * @return array<string, mixed>
     */
    private function decode(string $json): array
    {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    public function testFileNameIsFeedJson(): void
    {
        $this->assertSame('feed.json', (new JsonFeedBuilder())->getFileName());
    }

    public function testVersionTitleAndItemRequiredFieldsArePresent(): void
    {
        $feed = $this->decode((new JsonFeedBuilder())->build($this->channel(), [$this->item()], $this->now()));

        $this->assertSame('https://jsonfeed.org/version/1.1', $feed['version']);
        $this->assertSame('Site', $feed['title']);
        $this->assertSame('https://example.com/a.html', $feed['items'][0]['id']);
        $this->assertSame('<p>Body</p>', $feed['items'][0]['content_html']);
    }

    public function testAuthorAndTagsAppearOnlyWhenPresent(): void
    {
        $with = $this->decode((new JsonFeedBuilder())->build(
            $this->channel(),
            [$this->item(author: 'Ada', tags: ['x'])],
            $this->now()
        ))['items'][0];
        $without = $this->decode((new JsonFeedBuilder())->build($this->channel(), [$this->item()], $this->now()))['items'][0];

        $this->assertSame([['name' => 'Ada']], $with['authors']);
        $this->assertSame(['x'], $with['tags']);
        $this->assertArrayNotHasKey('authors', $without);
        $this->assertArrayNotHasKey('tags', $without);
    }

    public function testEmptyItemsEncodeAsAnEmptyJsonArrayNotAnObject(): void
    {
        $json = (new JsonFeedBuilder())->build($this->channel(), [], $this->now());

        $this->assertStringContainsString('"items":[]', $json);
    }

    public function testEmojiAndSlashesAreNotEscaped(): void
    {
        $json = (new JsonFeedBuilder())->build($this->channel(), [$this->item('Party 🎉')], $this->now());

        $this->assertStringContainsString('Party 🎉', $json);
        $this->assertStringContainsString('https://example.com/a.html', $json);
        $this->assertStringNotContainsString('\/', $json);
    }

    public function testInvalidUtf8InAnyFieldIsScrubbedInsteadOfThrowing(): void
    {
        $bad = "bad \xFF \xC3\x28 byte";
        $item = $this->item($bad, "<p>{$bad}</p>", $bad, author: $bad, tags: [$bad], url: "https://example.com/{$bad}");
        $channel = $this->channel();
        $channel['title'] = $bad;
        $channel['description'] = $bad;

        $json = (new JsonFeedBuilder())->build($channel, [$item], $this->now());

        $feed = $this->decode($json);
        $this->assertStringContainsString('bad', $feed['title']);
        $this->assertStringContainsString('bad', $feed['items'][0]['title']);
    }

    public function testLanguageIsIncludedOnlyWhenProvided(): void
    {
        $channel = $this->channel();
        $this->assertArrayNotHasKey('language', $this->decode((new JsonFeedBuilder())->build($channel, [], $this->now())));

        $channel['language'] = 'de';
        $this->assertSame('de', $this->decode((new JsonFeedBuilder())->build($channel, [], $this->now()))['language']);
    }
}
