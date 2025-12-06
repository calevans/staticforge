<?php

declare(strict_types=1);

namespace EICC\StaticForge\Shortcodes;

interface ShortcodeInterface
{
    /**
     * Get the name of the shortcode (the tag used in content)
     * e.g., 'youtube' for [[youtube]]
     */
    public function getName(): string;

    /**
     * Handle the shortcode execution
     *
     * @param array<string, string> $attributes Parsed attributes
     * @param string $content Inner content (for enclosing shortcodes)
     * @return string Rendered output
     */
    public function handle(array $attributes, string $content = ''): string;
}
