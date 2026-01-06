# Site Audit Feature Plan

## Overview
This document outlines the plan for a new `site:audit` command (or set of commands) designed to verify the health, quality, and configuration of a StaticForge site. The goal is to provide users with confidence that their site is built correctly, optimized for SEO, and deployed securely.

*Note: Configuration and environment checks are already handled by `site:check` and are excluded from this specific scope.*

## Proposed Command Structure
The primary command will be `site:audit`. It can run all checks by default or accept flags/arguments to target specific areas.

```bash
lando php bin/staticforge.php site:audit [options]
```
**Options:**
- `--content`: Run source content checks.
- `--build`: Run post-build output checks (links, assets).
- `--seo`: Run SEO and metadata checks.
- `--live`: Run live deployment checks (SSL, headers).
- `--url=`: Specify a target URL for live checks (overriding `siteconfig.yaml`).

## Audit Scopes

### 1. Source Content Integrity (Pre-Build)
**Goal:** Detect errors in the raw markdown and data before the build process begins. This saves time and catches logical errors early.

*   **Frontmatter Validation:**
    *   Scan all Markdown files in `content/`.
    *   Verify presence of required fields (e.g., `title`, `layout`).
    *   Check for valid date formats.
    *   Warning for posts remaining in `draft: true` status for extended periods (configurable).
*   **Taxonomy Consistency:**
    *   Identify tags or categories used in content that are not defined in the site configuration (if strict taxonomy is enabled).
    *   Identify "orphan" tags/categories (defined in config but never used in content).
*   **Markdown Link Validation:**
    *   Regex scan of Markdown files for internal links `[Label](path/to/file.md)`.
    *   Verify that the target file exists on the filesystem.

### 2. Output Quality & Integrity (Post-Build)
**Goal:** Analyze the generated HTML in the `public/` (or `release/`) directory to ensure the final product is cohesive.

*   **Internal Dead Link Checker:**
    *   Crawl generated HTML files.
    *   Verify every local `href` points to a valid file (`.html`, `.php` if applicable, assets).
    *   Flag broken anchors (e.g., `page.html#section`).
*   **Asset Verification:**
    *   Scan `<img>`, `<script>`, and `<link>` tags.
    *   Ensure referenced files exist in the output directory.
    *   **Image Optimization:** Flag images exceeding a certain size threshold (e.g., >1MB) or uncompressed formats.
    *   **Accessibility:** Scan for missing or empty `alt` attributes on images.

### 3. SEO & Metadata
**Goal:** Ensure the site is optimized for search engines and social sharing.

*   **Meta Tags:**
    *   Verify every page has a unique `<title>` tag.
    *   Verify presence of `<meta name="description">`.
    *   Check for duplicate meta descriptions across pages.
*   **Social & Open Graph:**
    *   Check for Open Graph tags (`og:title`, `og:image`, `og:url`).
    *   Check for Twitter Card tags.
*   **Sitemap & Robots:**
    *   Validate `sitemap.xml` exists and is valid XML.
    *   Ensure `robots.txt` exists and does not block the entire site (unless configured to).
*   **Canonical URLs:**
    *   Verify `<link rel="canonical">` tags exist and point to the correct `baseUrl`.

### 4. Deployment & Live Health (Remote Checks)
**Goal:** Verify the site as seen by the outside world. This requires network requests to the live `baseUrl`.

*   **SSL/TLS Validity:**
    *   Check certificate expiration date (Warn if < 14 days).
    *   Verify certificate issuer and chain validity.
    *   Check for HTTP -> HTTPS redirection.
*   **Security Headers:**
    *   Audit response headers for best practices:
        *   `Strict-Transport-Security` (HSTS)
        *   `X-Content-Type-Options`
        *   `X-Frame-Options`
        *   `Content-Security-Policy`
*   **HTTP Status Health:**
    *   Ping `baseUrl` to ensure 200 OK.
    *   Ping a known non-existent URL (e.g., `/matches-nothing-404-test`) to ensure it returns a 404 status code (detect "Soft 404" configurations).
*   **Basic Performance:**
    *   Time To First Byte (TTFB) measurement.
    *   Check for compression headers (`Content-Encoding: gzip/brotli`).

## Technical Implementation Notes

### Architecture
*   Create a new Service: `App\Services\AuditService`.
*   Implement granular "Auditor" classes (e.g., `ContentAuditor`, `SemAuditor`, `SslAuditor`) implementing a common interface.
*   Use `Symfony\Component\Console\Style\SymfonyStyle` for reporting results in the CLI (tables, success/error blocks).

### Dependencies (Potential)
*   **HTTP Requests:** *Guzzle* or *Symfony HttpClient* (likely already available or easy to add) for crawling and live checks.
*   **HTML Parsing:** *Symfony DomCrawler* for analyzing generated HTML efficiently.
*   **SSL Checks:** PHP's built-in stream context options or a lightweight wrapper to inspect certificates.

### Configuration
*   Allow overriding thresholds (e.g., max image size) via `siteconfig.yaml` under an `audit:` key.

```yaml
audit:
  image_size_limit: 500kb
  require_og_tags: true
  ignore_links:
    - "https://example.com/broken-but-ignored"
```
