<?php

declare(strict_types=1);

namespace EICC\StaticForge\Shortcodes;

class AlertShortcode extends BaseShortcode
{
    public function getName(): string
    {
        return 'alert';
    }

    public function handle(array $attributes, string $content = ''): string
    {
        $type = $attributes['type'] ?? 'info';

        // Process markdown in content if needed
        // Since this runs before MarkdownRenderer, the content inside might be markdown.
        // If we output HTML, the MarkdownRenderer might not process the inside if it's wrapped in div?
        // Markdown usually processes content inside block tags if configured.
        // But here we are replacing [[alert]]...[[/alert]] with <div class="alert">...</div>.
        // If we return HTML, MarkdownRenderer will see HTML.
        // Standard Markdown parsers treat HTML blocks as raw HTML and don't process markdown inside them unless configured (markdown="1").
        // However, we have `processMarkdown($content)` helper in BaseShortcode.
        // If we use it, we convert inner markdown to HTML *now*.
        // This is probably safer.

        $processedContent = $this->processMarkdown($content);

        return $this->render('shortcodes/alert.twig', [
            'type' => $type,
            'content' => $processedContent
        ]);
    }
}
