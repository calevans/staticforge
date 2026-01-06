# Implementation Plan: audit:links

## Overview
This plan details the creation of the `audit:links` command. This command is responsible for validating the integrity of hyperlinks in the generated static site. It will scan the output directory (Post-Build) and verify both internal navigation and external references.

## Goals
1.  **New Command**: Create `src/Commands/Audit/LinksCommand.php`.
2.  **Scope**:
    *   **Internal Links**: verify that local files exist and are reachable.
    *   **External Links**: verify that remote URLs return a success status code (200-299).
3.  **Filtration**: Support flags to run only specific checks (`--internal`, `--external`).

## Prerequisites (Dependencies)
To implement this robustness and efficiently (especially for external links which can be slow), we should add two Symfony components:
*   `symfony/dom-crawler`: For easy extraction of `<a>` tags from HTML.
*   `symfony/http-client`: For asynchronous/parallel checking of external links (crucial for performance).

*Note: If adding dependencies is discouraged, we can use `DOMDocument` and `curl`, but specific packages are recommended for maintainability.*

## Proposed Command Class
**File**: `src/Commands/Audit/LinksCommand.php`
**Signature**: `audit:links [--internal] [--external] [--concurrency=10]`

### Logic Flow

1.  **Setup**:
    *   Locate `OUTPUT_DIR` (from container/env, usually `public/`).
    *   Check if directory exists.

2.  **Discovery**:
    *   Recursive scan of `OUTPUT_DIR` for `.html` files.
    *   Initialize `LinkExtractor` service (or private method) to parse HTML and find attributes:
        *   `href` on `<a>`
        *   (Optional) `src` on `<img>` if we want to check broken images here too, or keep that for `audit:content`. Let's stick to `<a>` for links command.

3.  **Categorization**:
    *   **Internal**:
        *   Starts with `/` (root relative).
        *   Relative paths (`../foo`).
        *   Anchors (`#foo`, `page.html#foo`).
    *   **External**:
        *   Starts with `http://` or `https://`.
    *   **Ignored**:
        *   `mailto:`, `tel:`, `#` (empty anchor).

4.  **Internal Validation Loop**:
    *   Resolves the link path relative to the current file's location or site root.
    *   Checks `file_exists()`.
    *   (Advanced) If link has hash `#section`, check if target file has `id="section"` or `name="section"`.

5.  **External Validation Loop**:
    *   If `--external` (or default) is active.
    *   Collect all unique external URLs.
    *   Use `HttpClient` to send asynchronous `HEAD` (or `GET` if HEAD fails) requests.
    *   Report Failures: 4xx, 5xx, or Timeout.

6.  **Reporting**:
    *   Output progress bar during scanning.
    *   Summary table of broken links:
        *   Source Page
        *   Broken Link (URL)
        *   Reason (404, File Not Found, etc.)

## Detailed Tasks
1.  **Install Dependencies**:
    *   `lando composer require symfony/dom-crawler symfony/http-client symfony/css-selector`
2.  **Create Command**: `src/Commands/Audit/LinksCommand.php`.
3.  **Implement Link Resolver**: Helper to convert `/path/to/page` -> `/var/www/public/path/to/page.html` (handle trailing slashes resolving to `index.html`).
4.  **Implement External Checker**: Logic to batch requests.

## Usage
*   `audit:links` - Checks everything.
*   `audit:links --internal` - Fast check of local files.
*   `audit:links --external` - Only check remote URLs.
