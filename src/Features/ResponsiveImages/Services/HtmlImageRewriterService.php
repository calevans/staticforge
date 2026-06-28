<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\ResponsiveImages\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use EICC\Utils\Container;
use EICC\Utils\Log;

/**
 * Owns DOM parsing/rewriting and source-path resolution for the
 * ResponsiveImages feature. Mirrors TableOfContentsService's
 * DOMDocument/DOMXPath usage.
 *
 * @phpstan-import-type ImageVariant from ImageVariantGenerator
 */
final class HtmlImageRewriterService
{
    public function __construct(
        private readonly Log $logger,
        private readonly ImageVariantGenerator $generator,
        private readonly ResponsiveImageConfig $config,
    ) {
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handlePostRender(Container $container, array $parameters): array
    {
        $html = $parameters['rendered_content'] ?? null;
        if (!is_string($html) || stripos($html, '<img') === false) {
            return $parameters;
        }

        $sourceDir = $container->getVariable('SOURCE_DIR');
        $outputDir = $container->getVariable('OUTPUT_DIR');
        $templateDir = $container->getVariable('TEMPLATE_DIR');
        $templateName = $container->getVariable('TEMPLATE') ?? 'sample';

        if (!is_string($sourceDir) || !is_string($outputDir) || !is_string($templateDir)) {
            return $parameters;
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $imgs = $xpath->query('//img[@src]');
        if ($imgs === false || $imgs->length === 0) {
            return $parameters;
        }

        $changed = false;
        foreach ($imgs as $img) {
            if (!$img instanceof DOMElement) {
                continue;
            }
            if ($this->rewriteOneImage($dom, $img, $sourceDir, $outputDir, $templateDir, (string) $templateName)) {
                $changed = true;
            }
        }

        if ($changed) {
            $saved = $dom->saveHTML();
            if ($saved !== false) {
                $parameters['rendered_content'] = $saved;
                $filePath = $parameters['file_path'] ?? 'unknown';
                $this->logger->log('INFO', "ResponsiveImages: rewrote <img> tags for {$filePath}");
            }
        }

        return $parameters;
    }

    private function rewriteOneImage(
        DOMDocument $dom,
        DOMElement $img,
        string $sourceDir,
        string $outputDir,
        string $templateDir,
        string $templateName
    ): bool {
        $src = $img->getAttribute('src');

        if ($this->isExternalOrSkippable($src)) {
            return false;
        }

        $sourcePath = $this->resolveSourcePath($src, $sourceDir, $templateDir, $templateName);
        if ($sourcePath === null) {
            return false;
        }

        $outputBaseDir = $outputDir . '/' . $this->config->outputDir;
        $urlBaseDir = '/' . $this->config->outputDir;

        $variants = $this->generator->generateVariants($sourcePath, $outputBaseDir, $urlBaseDir);
        if (empty($variants)) {
            return false;
        }

        $this->replaceWithPicture($dom, $img, $variants);
        return true;
    }

    private function isExternalOrSkippable(string $src): bool
    {
        return $src === ''
            || str_starts_with($src, 'http://')
            || str_starts_with($src, 'https://')
            || str_starts_with($src, '//')
            || str_starts_with($src, 'data:');
    }

    /**
     * Resolve a root-relative /assets/... src to a real filesystem path.
     * Mirrors TemplateAssetsService's two-tier precedence: content assets
     * override template assets, so check content first.
     *
     * Defends against path traversal: the resolved realpath() must remain
     * within the realpath() of the expected source/template root, otherwise
     * the candidate is rejected.
     */
    private function resolveSourcePath(
        string $src,
        string $sourceDir,
        string $templateDir,
        string $templateName
    ): ?string {
        $relative = ltrim($src, '/');

        $contentRoot = realpath($sourceDir);
        if ($contentRoot !== false) {
            $candidate = $this->safeRealpathWithin($contentRoot, $contentRoot . '/' . $relative);
            if ($candidate !== null) {
                return $candidate;
            }
        }

        $templateRoot = realpath($templateDir . '/' . $templateName);
        if ($templateRoot !== false) {
            $candidate = $this->safeRealpathWithin($templateRoot, $templateRoot . '/' . $relative);
            if ($candidate !== null) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Resolve $candidatePath to a realpath and verify it is readable and
     * remains within $root (defense against ../ path traversal).
     */
    private function safeRealpathWithin(string $root, string $candidatePath): ?string
    {
        $resolved = realpath($candidatePath);
        if ($resolved === false || !is_readable($resolved)) {
            return null;
        }

        if (!str_starts_with($resolved, $root . DIRECTORY_SEPARATOR) && $resolved !== $root) {
            return null;
        }

        return $resolved;
    }

    /**
     * @param list<array{width: int, path: string, url: string, format: string}> $variants
     */
    private function replaceWithPicture(DOMDocument $dom, DOMElement $img, array $variants): void
    {
        $picture = $dom->createElement('picture');

        $webpVariants = array_values(array_filter($variants, fn (array $v): bool => $v['format'] === 'webp'));
        if (!empty($webpVariants)) {
            $source = $dom->createElement('source');
            $source->setAttribute('type', 'image/webp');
            $source->setAttribute('srcset', $this->buildSrcset($webpVariants));
            $picture->appendChild($source);
        }

        $originalVariants = array_values(array_filter($variants, fn (array $v): bool => $v['format'] === 'original'));

        /** @var DOMElement $newImg */
        $newImg = $img->cloneNode(false);
        if (!empty($originalVariants)) {
            $newImg->setAttribute('srcset', $this->buildSrcset($originalVariants));
            $newImg->setAttribute('sizes', '100vw');
            $largest = end($originalVariants);
            $newImg->setAttribute('src', $largest['url']);
        }
        $picture->appendChild($newImg);

        $parent = $img->parentNode;
        if ($parent !== null) {
            $parent->replaceChild($picture, $img);
        }
    }

    /**
     * @param list<array{width: int, path: string, url: string, format: string}> $variants
     */
    private function buildSrcset(array $variants): string
    {
        return implode(', ', array_map(
            fn (array $v): string => "{$v['url']} {$v['width']}w",
            $variants
        ));
    }
}
