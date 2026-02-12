<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\EstimatedReadingTime;

class EstimatedReadingTimeService
{
    /**
     * Calculate reading time in minutes and generate a label
     *
     * @param string $content The content to analyze
     * @param int $wpm Words per minute (default: 200)
     * @param string $singular Label for 1 minute (default: "min read")
     * @param string $plural Label for >1 minutes (default: "min read")
     * @return array{minutes: int, label: string}
     */
    public function calculate(string $content, int $wpm = 200, string $singular = 'min read', string $plural = 'min read'): array
    {
        // Strip HTML tags
        $text = strip_tags($content);

        // Count words (str_word_count is locale-dependent, but good enough for now)
        $wordCount = str_word_count($text);

        // Calculate minutes, rounding up
        $minutes = (int) ceil($wordCount / $wpm);

        // Ensure at least 1 minute
        if ($minutes < 1) {
            $minutes = 1;
        }

        // Determine label
        $label = ($minutes === 1) ? $singular : $plural;

        return [
            'minutes' => $minutes,
            'label' => "{$minutes} {$label}",
        ];
    }
}
