---
name: create-shortcode
description: Scaffold a new Shortcode class following the project's BaseShortcode pattern. Use when adding new dynamic content tags like [[youtube]] or [[alert]].
---

# Create Shortcode

## When to use this
Use this skill when you need to:
- Create a new Shortcode for the site generator.
- Implement dynamic tags (e.g. `[[my-tag]]`).
- Follow the standard `BaseShortcode` implementation pattern.

## Implementation Guide

1.  **File Location**: `src/Shortcodes/YourShortcode.php`
2.  **Parent Class**: Must extend `EICC\StaticForge\Shortcodes\BaseShortcode`
3.  **Registration**: Must be registered in `src/Shortcodes/ShortcodeManager.php` (or via a Feature).

## Template

```php
<?php

declare(strict_types=1);

namespace EICC\StaticForge\Shortcodes;

use EICC\Utils\Container;

class NewShortcode extends BaseShortcode
{
    public function getName(): string
    {
        // The tag name used in markdown: [[tagname]]
        return 'tagname';
    }

    /**
     * Handle the shortcode rendering
     *
     * @param array<string, string> $attributes Key-value pairs from the tag
     * @param string $content The content between opening and closing tags (if any)
     * @return string The HTML to replace the tag with
     */
    public function handle(array $attributes, string $content = ''): string
    {
        // Example: Get an attribute with a default
        $title = $attributes['title'] ?? 'Default Title';

        // Option A: Return simple string
        // return "<div class='alert'>{$content}</div>";

        // Option B: Render a Twig template (Preferred)
        // return $this->render('shortcodes/my-template.twig', [
        //     'title' => $title,
        //     'content' => $content
        // ]);
    }
}
```
