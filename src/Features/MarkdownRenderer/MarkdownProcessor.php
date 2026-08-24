<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\MarkdownRenderer;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

class MarkdownProcessor
{
    private MarkdownConverter $converter;

    /**
     * @param bool $trustHtml Whether raw HTML in Markdown source is passed through
     *   unchanged (current default) or escaped. Content authors have always been able
     *   to write raw HTML in this project's Markdown files; flipping this to false is
     *   a deliberate per-site opt-in via siteconfig.yaml's `markdown.trust_html`, not
     *   something this constructor decides on its own.
     */
    public function __construct(bool $trustHtml = true)
    {
        // Create the CommonMark environment and converter with table support
        $environment = new Environment();
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new TableExtension());

        // Configure HeadingPermalinkExtension to use an empty symbol (invisible link)
        $environment->addExtension(new HeadingPermalinkExtension());
        $environment->mergeConfig([
            'heading_permalink' => [
                'symbol' => '',
                'insert' => 'after',
            ],
            'html_input' => $trustHtml ? 'allow' : 'escape',
        ]);

        $this->converter = new MarkdownConverter($environment);
    }

    public function convert(string $markdown): string
    {
        return $this->converter->convert($markdown)->getContent();
    }
}
