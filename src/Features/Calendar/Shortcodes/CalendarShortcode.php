<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\Calendar\Shortcodes;

use EICC\StaticForge\Shortcodes\BaseShortcode;
use EICC\StaticForge\Features\Calendar\Services\CalendarService;
use EICC\Utils\Container;

class CalendarShortcode extends BaseShortcode
{
    private CalendarService $calendarService;

    public function __construct(CalendarService $calendarService, Container $container)
    {
        $this->calendarService = $calendarService;
        $this->container = $container;
    }

    public function getName(): string
    {
        return 'calendar';
    }

    /**
     * Handle the shortcode
     *
     * @param array<string, string> $attributes
     * @param string $content
     * @return string
     */
    public function handle(array $attributes, string $content = ''): string
    {
        $calendarName = $attributes['name'] ?? null;
        if (!$calendarName) {
            return '<!-- Calendar shortcode error: name attribute is required -->';
        }

        // Get config from siteconfig.yaml
        $siteConfig = $this->container->getVariable('site_config') ?? [];
        $calendarConfig = $siteConfig['calendars'][$calendarName] ?? [];

        // Merge attributes over config (attributes take precedence)
        $view = $attributes['view'] ?? $calendarConfig['view'] ?? 'month';
        $start = $attributes['start'] ?? $calendarConfig['start'] ?? 'today';
        $end = $attributes['end'] ?? $calendarConfig['end'] ?? '+1 year';
        $template = $attributes['template'] ?? $calendarConfig['template'] ?? 'calendar-wrapper';

        // Security Check: Template Traversal
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $template)) {
            $template = 'calendar-wrapper';
        }

        // Get filtered events
        $events = $this->calendarService->getEvents($calendarName, $start, $end);

        // Render template
        // Note: 'calendar-wrapper' implies a twig file 'templates/shortcodes/calendar-wrapper.twig'
        // If template attribute is just 'default', we might map it to 'calendar-wrapper'.
        if ($template === 'default') {
            $template = 'calendar-wrapper';
        }
        
        // Ensure template has path prefix if needed, or assume ShortcodeRenderer handles it.
        // TemplateRenderer usually looks in templates/ directory.
        // Convention for shortcodes is often just the filename or path.
        // Let's assume 'shortcodes/calendar-wrapper.twig'.
        
        $templatePath = "shortcodes/{$template}.twig";

        return $this->render($templatePath, [
            'calendar_name' => $calendarName,
            'view' => $view,
            'start' => $start,
            'end' => $end,
            'events' => $events,
            // JSON payload for JS
            'events_json' => json_encode($events, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
            'unique_id' => 'calendar-' . uniqid()
        ]);
    }
}
