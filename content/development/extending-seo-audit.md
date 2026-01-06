---
title: 'Extending SEO Audit'
description: 'How to implement custom SEO checks using the SEO_AUDIT_PAGE event.'
template: docs
menu: '4.1.8'
url: "https://calevans.com/staticforge/development/extending-seo-audit.html"
---

# Extending the SEO Audit

The `audit:seo` command provides a core set of SEO and metadata checks (Title, Description, Canonical URL). However, extensions and features often inject specialized metadata (e.g., Open Graph, Twitter Cards, Schema.org) that should also be validated.

StaticForge provides the `SEO_AUDIT_PAGE` event to allow Features to hook into the audit process and report their own issues.

## Event Name
`SEO_AUDIT_PAGE`

## Event Parameters
The event passes an array with the following keys:

| Key | Type | Description |
| :--- | :--- | :--- |

| `crawler` | `Symfony\Component\DomCrawler\Crawler` | The DOM crawler instance for the page being audited. Use this to inspect the HTML. |
| `filename` | `string` | The relative path of the file being audited (e.g. `index.html`, `blog/post.html`). |
| `issues` | `array` | An array of issues found so far. You should append your findings to this array. |

## Implementing a Listener

To add custom checks, register a listener for `SEO_AUDIT_PAGE` in your Feature class.

```php
// In YourFeature::register()
$eventManager->registerListener('SEO_AUDIT_PAGE', [$this, 'auditPage']);
```

Then, implement the callback method:

```php
public function auditPage(Container $container, array $params): array
{
    $crawler = $params['crawler'];
    $filename = $params['filename'];
    $issues = $params['issues'];

    // Example: Check for a custom meta tag
    $customMeta = $crawler->filter('meta[name="my-custom-meta"]');

    if ($customMeta->count() === 0) {
        $issues[] = [
            'file' => $filename,
            'type' => 'warning', // 'warning' or 'error'
            'message' => 'Missing <meta name="my-custom-meta"> tag'
        ];
    }

    // Always return the modified params
    $params['issues'] = $issues;
    return $params;
}
```

## Issue Structure
Each issue pushed to the `$issues` array must be an associative array with:
*   `file`: The filename (passed in params).
*   `type`: String `'error'` (fails build) or `'warning'` (just notifies).
*   `message`: A concise description of the problem.
