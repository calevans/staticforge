<?php

declare(strict_types=1);

namespace EICC\StaticForge\Shortcodes;

class YoutubeShortcode extends BaseShortcode
{
    public function getName(): string
    {
        return 'youtube';
    }

    public function handle(array $attributes, string $content = ''): string
    {
        $id = $attributes['id'] ?? '';

        if (empty($id)) {
            return '<!-- Youtube shortcode missing id -->';
        }

        return $this->render('shortcodes/youtube.twig', [
            'id' => $id,
            'width' => $attributes['width'] ?? '560',
            'height' => $attributes['height'] ?? '315',
            'title' => $attributes['title'] ?? 'YouTube video player'
        ]);
    }
}
