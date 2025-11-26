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

    public function __construct()
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
        ]);

        $this->converter = new MarkdownConverter($environment);
    }

    public function convert(string $markdown): string
    {
        return $this->converter->convert($markdown)->getContent();
    }
}
