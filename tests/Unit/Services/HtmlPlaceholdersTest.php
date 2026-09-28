<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Services;

use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Services\HtmlPlaceholders;
use PHPUnit\Framework\TestCase;

class HtmlPlaceholdersTest extends TestCase
{
    private function makeEvent(): RenderEvent
    {
        return new RenderEvent(
            name: 'PRE_RENDER',
            filePath: '/source/test.md',
            fileUrl: '',
            metadata: [],
        );
    }

    public function testReserveReturnsAnAlphanumericToken(): void
    {
        $event = $this->makeEvent();

        $token = HtmlPlaceholders::reserve($event, '<div>Trusted HTML</div>');

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]+$/', $token);
    }

    public function testReserveReturnsDistinctTokensForEachCall(): void
    {
        $event = $this->makeEvent();

        $first = HtmlPlaceholders::reserve($event, '<div>One</div>');
        $second = HtmlPlaceholders::reserve($event, '<div>Two</div>');

        $this->assertNotSame($first, $second);
    }

    public function testRestoreReplacesBareInlineToken(): void
    {
        $event = $this->makeEvent();
        $token = HtmlPlaceholders::reserve($event, '<span>inline</span>');

        $result = HtmlPlaceholders::restore($event, "Before {$token} After");

        $this->assertSame('Before <span>inline</span> After', $result);
    }

    public function testRestoreUnwrapsTokenThatMarkdownPutInsideAParagraph(): void
    {
        $event = $this->makeEvent();
        $token = HtmlPlaceholders::reserve($event, '<div class="alert">Block</div>');

        // Markdown wraps a lone-line token in <p>...</p>; the block element must
        // not end up nested inside that paragraph.
        $result = HtmlPlaceholders::restore($event, "<p>{$token}</p>");

        $this->assertSame('<div class="alert">Block</div>', $result);
    }

    public function testRestoreReplacesMultipleReservedPlaceholders(): void
    {
        $event = $this->makeEvent();
        $tokenA = HtmlPlaceholders::reserve($event, '<div>A</div>');
        $tokenB = HtmlPlaceholders::reserve($event, '<div>B</div>');

        $result = HtmlPlaceholders::restore($event, "<p>{$tokenA}</p><p>{$tokenB}</p>");

        $this->assertSame('<div>A</div><div>B</div>', $result);
    }

    public function testRestoreIsNoOpWhenNothingWasReserved(): void
    {
        $event = $this->makeEvent();

        $content = 'Nothing to restore here.';
        $result = HtmlPlaceholders::restore($event, $content);

        $this->assertSame($content, $result);
    }
    public function testRestoreEscapesTokenInsideATag(): void
    {
        $event = $this->makeEvent();
        $token = HtmlPlaceholders::reserve($event, "<a href=\"x onmouseover=alert(1)\">y</a>");

        $result = HtmlPlaceholders::restore($event, "<p><a href=\"{$token}\">link</a></p>");

        $this->assertStringNotContainsString("<a href=\"x onmouseover", $result);
        $this->assertStringContainsString("href=\"&lt;a href=&quot;x onmouseover=alert(1)&quot;&gt;y&lt;/a&gt;\"", $result);
    }

    public function testRestoreEscapesTokenInsideCode(): void
    {
        $event = $this->makeEvent();
        $token = HtmlPlaceholders::reserve($event, "<div>A</div>");

        $result = HtmlPlaceholders::restore($event, "<pre><code>{$token}</code></pre><p>{$token}</p>");

        $this->assertSame("<pre><code>&lt;div&gt;A&lt;/div&gt;</code></pre><div>A</div>", $result);
    }
}
