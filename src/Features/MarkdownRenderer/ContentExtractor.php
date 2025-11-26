<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\MarkdownRenderer;

class ContentExtractor
{
    /**
     * Extract markdown content, skipping frontmatter
     *
     * @param string $content Full file content
     * @return string Markdown content without frontmatter
     */
    public function extractMarkdownContent(string $content): string
    {
        // Check for INI frontmatter (--- ... ---)
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches)) {
            return trim($matches[2]);
        }

        return $content;
    }

    /**
     * Extract title from HTML content if not in metadata
     */
    public function extractTitleFromContent(string $htmlContent): string
    {
        // Look for first h1-h6 tag
        if (preg_match('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/i', $htmlContent, $matches)) {
            return strip_tags($matches[1]);
        }

        return 'Untitled';
    }
}
