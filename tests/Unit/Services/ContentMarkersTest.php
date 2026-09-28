<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Services;

use EICC\StaticForge\Services\ContentMarkers;
use PHPUnit\Framework\TestCase;

class ContentMarkersTest extends TestCase
{
    public function testWrapSurroundsContentWithStartAndEndMarkers(): void
    {
        $wrapped = ContentMarkers::wrap('<p>Hello</p>');

        $this->assertSame(
            '<!--sf:content--><p>Hello</p><!--/sf:content-->',
            $wrapped
        );
    }

    public function testExtractReturnsOriginalContentFromWrappedHtml(): void
    {
        $wrapped = ContentMarkers::wrap('<p>Hello</p>');
        $page = '<html><body><nav>Menu</nav>' . $wrapped . '<footer>Bye</footer></body></html>';

        $this->assertSame('<p>Hello</p>', ContentMarkers::extract($page));
    }

    public function testExtractRoundTripsEmptyContent(): void
    {
        $wrapped = ContentMarkers::wrap('');

        $this->assertSame('', ContentMarkers::extract($wrapped));
    }

    public function testExtractReturnsNullWhenNoMarkersPresent(): void
    {
        $this->assertNull(ContentMarkers::extract('<p>Plain content, never wrapped.</p>'));
    }

    public function testExtractReturnsNullWhenOnlyStartMarkerPresent(): void
    {
        $html = ContentMarkers::START . '<p>Truncated, missing the closing marker</p>';

        $this->assertNull(ContentMarkers::extract($html));
    }

    public function testExtractReturnsNullWhenOnlyEndMarkerPresent(): void
    {
        $html = '<p>Missing the opening marker</p>' . ContentMarkers::END;

        $this->assertNull(ContentMarkers::extract($html));
    }
}
