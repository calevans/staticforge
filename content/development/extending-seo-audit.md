---
title: 'Extending SEO Audit'
description: 'How to implement custom SEO checks using the SEO_AUDIT_PAGE event.'
template: docs
menu: '4.1.8'
og_image: "Robotic auditor holding a clipboard checking a website, magnifying glass over HTML code, SEO verification, 3d render, clean style, --ar 16:9"
---

# Extending the SEO Audit (For Type A Personalities)

The `audit:seo` command is great. It catches the basics like missing titles and overly verbose descriptions. But if you have specific requirements—like checking for Open Graph tags, verifying Twitter Cards, or ensuring specialized Schema.org data—you need more power.

StaticForge has you covered with the `SEO_AUDIT_PAGE` event.

## The Hook: `SEO_AUDIT_PAGE`

This event fires for **every single HTML file** during an audit. It hands you the DOM and asks, "Do you have any complaints?"

### The Event: `SeoAuditPageEvent`

You receive one object with three properties:

| Property | Type | Description |
| :--- | :--- | :--- |
| `$event->crawler` | `Symfony\Component\DomCrawler\Crawler` (read-only) | The DOM crawler instance. This is your scalpel. Use it to inspect the HTML. |
| `$event->filename` | `string` (read-only) | The path of the file you are looking at (e.g., `blog/my-post.html`). |
| `$event->issues` | `array` (mutable) | The list of problems found so far. Your job is to append to this list. |

---

## How to Implement a Custom Check

Let's say you want to enforce a rule that every page must have a strict Content Security Policy (CSP) meta tag.

### Step 1: Register the Listener

Put a `#[EventListener]` attribute directly on your handler method — `BaseFeature` scans for it and registers it automatically.

```php
// src/Features/SecurityAudit/Feature.php

use EICC\StaticForge\Core\Events\EventListener;
use EICC\StaticForge\Core\Events\SeoAuditPageEvent;

#[EventListener('SEO_AUDIT_PAGE')]
public function auditSecurityHeaders(SeoAuditPageEvent $event): void
{
    // implementation goes in Step 2
}
```

### Step 2: Write the Logic

Check the DOM and append to `$event->issues` — there's nothing to return.

```php
public function auditSecurityHeaders(SeoAuditPageEvent $event): void
{
    // Check for the meta tag
    $csp = $event->crawler->filter('meta[http-equiv="Content-Security-Policy"]');

    if ($csp->count() === 0) {
        // REPORT THE CRIME!
        $event->issues[] = [
            'file' => $event->filename,
            'type' => 'error', // Use 'error' to fail the build, 'warning' to just yell.
            'message' => 'Missing Content-Security-Policy meta tag.'
        ];
    }
}
```

---

## The Issue Structure

Each entry you append to `$event->issues` should follow this format strictly:

*   **`file`**: The filename (`$event->filename`).
*   **`type`**:
    *   `'error'`: Critical failure. If the build server sees this, it should fail.
    *   `'warning'`: Something to fix, but not a showstopper.
*   **`message`**: A concise, helpful description of what went wrong.

> **Pro Tip:** Don't be annoying with your warnings. If you flag every single page for a minor issue, users will just ignore all your warnings. Be precise.
