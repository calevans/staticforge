<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\EstimatedReadingTime;

use EICC\StaticForge\Features\EstimatedReadingTime\EstimatedReadingTimeService;
use PHPUnit\Framework\TestCase;

class EstimatedReadingTimeServiceTest extends TestCase
{
    private EstimatedReadingTimeService $service;

    protected function setUp(): void
    {
        $this->service = new EstimatedReadingTimeService();
    }

    public function testCalculateReturnsCorrectTime(): void
    {
        // 400 words at 200 wpm = 2 minutes
        $text = str_repeat('word ', 400);
        $result = $this->service->calculate($text);

        $this->assertEquals(2, $result['minutes']);
        $this->assertEquals('2 min read', $result['label']);
    }

    public function testCalculateHandlesShortText(): void
    {
        $text = 'Just a few words here.';
        $result = $this->service->calculate($text);

        $this->assertEquals(1, $result['minutes']);
        $this->assertEquals('1 min read', $result['label']);
    }

    public function testCalculateHandlesHtmlTags(): void
    {
        // HTML tags should be stripped and not counted as words
        $text = '<p>' . str_repeat('word ', 200) . '</p>';
        $result = $this->service->calculate($text); // 200 words = 1 min

        $this->assertEquals(1, $result['minutes']);
    }

    public function testCalculateRespectsWpm(): void
    {
        // 200 words at 100 wpm = 2 minutes
        $text = str_repeat('word ', 200);
        $result = $this->service->calculate($text, 100);

        $this->assertEquals(2, $result['minutes']);
    }

    public function testCalculateRespectsLabels(): void
    {
        $text = str_repeat('word ', 400); // 2 minutes
        $result = $this->service->calculate($text, 200, 'minute', 'minutes');

        $this->assertEquals('2 minutes', $result['label']);
    }
}
