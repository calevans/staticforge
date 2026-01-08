---
title: 'Extending SEO Audit'
description: 'How to implement custom SEO checks using the SEO_AUDIT_PAGE event.'
template: docs
menu: '4.1.8'
url: "https://calevans.com/staticforge/development/extending-seo-audit.html"
og_image: "Robotic auditor holding a clipboard checking a website, magnifying glass over HTML code, SEO verification, 3d render, clean style, --ar 16:9"
---

# Extending the SEO Audit (For Type A Personalities)

The `audit:seo` command is great. It catches the basics like missing titles and overly verbose descriptions. But if you have specific requirements—like checking for Open Graph tags, verifying Twitter Cards, or ensuring specialized Schema.org data—you need more power.

StaticForge has you covered with the `SEO_AUDIT_PAGE` event.

## The Hook: `SEO_AUDIT_PAGE`

This event fires for **every single HTML file** during an audit. It hands you the DOM and asks, "Do you have any complaints?"

### The Data payload

You receive an array with three keys:

| Key | Type | Description |
| :--- | :--- | :--- |
| `crawler` | `Symfony\Component\DomCrawler\Crawler` | The DOM crawler instance. This is your scalpel. Use it to inspect the HTML. |
| `filename` | `string` | The path of the file you are looking at (e.g., `blog/my-post.html`). |
| `issues` | `array` | The list of problems found so far. Your job is to add to this list. |

---

## How to Implement a Custom Check

Let's say you want to enforce a rule that every page must have a strict Content Security Policy (CSP) meta tag.

### Step 1: Register the Listener

In your Feature class, tell the EventManager you want to help with the audit.

```php
// src/Features/SecurityAudit/Feature.php

public function register(EventManager $eventManager, Container $container): void
{
    $eventManager->registerListener('SEO_AUDIT_PAGE', [$this, 'auditSecurityHeaders']);
}
```

### Step 2: Write the Logic

Now, implement the method. It receives the data, checks the DOM, and reports any failures.

```php
public function auditSecurityHeaders(Container $container, array $params): array
{
    // Unpack the tools
    $crawler = $params['crawler'];
    $filename = $params['filename'];
    $issues = $params['issues'];

    // Check for the meta tag
    $csp = $crawler->filter('meta[http-equiv="Content-Security-Policy"]');

    if ($csp->count() === 0) {
        // REPORT THE CRIME!
        $issues[] = [
            'file' => $filename,
            'type' => 'error', // Use 'error' to fail the build, 'warning' to just yell.
            'message' => 'Missing Content-Security-Policy meta tag.'
        ];
    }

    // Pack it back up and return it
    $params['issues'] = $issues;
    return $params;
}
```

---

## The Issue Structure

When you report an issue, follow this format strictly:

*   **`file`**: The filename (passed in params).
*   **`type`**:
    *   `'error'`: Critical failure. If the build server sees this, it should fail.
    *   `'warning'`: Something to fix, but not a showstopper.
*   **`message`**: A concise, helpful description of what went wrong.

> **Pro Tip:** Don't be annoying with your warnings. If you flag every single page for a minor issue, users will just ignore all your warnings. Be precise.
