<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\Calendar\Services;

use EICC\StaticForge\Features\MarkdownRenderer\MarkdownProcessor;
use EICC\Utils\Log;
use Symfony\Component\Yaml\Yaml;

class CalendarService
{
    private Log $logger;
    private MarkdownProcessor $markdownProcessor;
    private string $projectRoot;

    public function __construct(Log $logger, string $projectRoot)
    {
        $this->logger = $logger;
        $this->projectRoot = $projectRoot;
        $this->markdownProcessor = new MarkdownProcessor();
    }

    /**
     * Get events for a specific calendar, filtered by date range.
     *
     * @param string $calendarName
     * @param string $startDate Relative date string (e.g. "today", "-1 month")
     * @param string $endDate Relative date string (e.g. "+1 year")
     * @return array<int, array<string, mixed>>
     */
    public function getEvents(string $calendarName, string $startDate = 'today', string $endDate = '+1 year'): array
    {
        // FIX: Prevent Path Traversal
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $calendarName)) {
            $this->logger->log('WARNING', "Invalid calendar name attempted: {$calendarName}");
            return [];
        }

        $calendarPath = $this->projectRoot . '/content/calendars/' . $calendarName;

        if (!is_dir($calendarPath)) {
            $this->logger->log('WARNING', "Calendar directory not found: {$calendarPath}");
            return [];
        }

        $events = [];
        $files = glob($calendarPath . '/*.md');

        if ($files === false) {
            return [];
        }

        $startTs = strtotime($startDate);
        $endTs = strtotime($endDate);

        if ($startTs === false || $endTs === false) {
            $this->logger->log('ERROR', "Invalid date format for calendar {$calendarName}");
            return [];
        }

        foreach ($files as $file) {
            $event = $this->parseEventFile($file);
            if (!$event) {
                continue;
            }

            $eventDateTs = strtotime($event['date']);
            if ($eventDateTs >= $startTs && $eventDateTs <= $endTs) {
                $events[] = $event;
            }
        }

        // Sort events by date
        usort($events, fn($a, $b) => strcmp($a['date'], $b['date']));

        return $events;
    }

    /**
     * Parse an event Markdown file.
     *
     * @param string $filePath
     * @return array<string, mixed>|null
     */
    private function parseEventFile(string $filePath): ?array
    {
        $content = @file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        // Split Frontmatter
        // FIX: Capture only content inside delimiters to avoid "multiple documents" error in Yaml parser
        // Updated regex to support cross-platform line endings (\r\n)
        if (!preg_match('/^---\s*[\r\n]+(.*?)[\r\n]+---\s*[\r\n]+(.*)$/s', $content, $matches)) {
            $this->logger->log('WARNING', "Invalid frontmatter in calendar file: {$filePath}");
            return null;
        }

        $frontmatterRaw = $matches[1];
        $bodyRaw = $matches[2];

        try {
            $frontmatter = Yaml::parse($frontmatterRaw);
        } catch (\Exception $e) {
            $this->logger->log('ERROR', "YAML parse error in {$filePath}: " . $e->getMessage());
            return null;
        }

        if (empty($frontmatter['title']) || empty($frontmatter['date'])) {
            $this->logger->log('WARNING', "Missing title or date in {$filePath}");
            return null;
        }

        // Optimize: Convert markdown once
        $description = $this->markdownProcessor->convert($bodyRaw);

        return [
            'id' => basename($filePath, '.md'),
            'title' => $frontmatter['title'],
            'start' => $frontmatter['date'], // Map 'date' to 'start' for JS
            'end' => $frontmatter['end_time'] ?? null, // Map end_time if exists (simple mapping)
            'location' => $frontmatter['location'] ?? '',
            'description' => $description, // JS uses 'description'
            // Keep original keys if needed for debugging or templates
            'date' => $frontmatter['date'],
            'description_html' => $description,
        ];
    }
}
