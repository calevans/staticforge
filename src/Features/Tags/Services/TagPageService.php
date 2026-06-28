<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\Tags\Services;

use EICC\StaticForge\Core\Application;
use EICC\StaticForge\Features\Tags\Models\Tag;
use EICC\StaticForge\Features\Tags\Models\TagFile;
use EICC\StaticForge\Services\TemplateRenderer;
use EICC\Utils\Container;
use EICC\Utils\Log;

class TagPageService
{
    private Log $logger;
    private TagsService $tagsService;
    private PaginationService $paginationService;
    private TemplateRenderer $templateRenderer;
    private int $itemsPerPage;

    public function __construct(
        Log $logger,
        TagsService $tagsService,
        PaginationService $paginationService,
        TemplateRenderer $templateRenderer,
        int $itemsPerPage = 10
    ) {
        $this->logger = $logger;
        $this->tagsService = $tagsService;
        $this->paginationService = $paginationService;
        $this->templateRenderer = $templateRenderer;
        $this->itemsPerPage = $itemsPerPage;
    }

    public function generateTagPages(Container $container): void
    {
        $tagIndex = $this->tagsService->getTagIndex();

        if (empty($tagIndex)) {
            return;
        }

        $this->logger->log('INFO', 'Generating ' . count($tagIndex) . ' tag archive pages');

        $application = $container->get(Application::class);

        foreach ($tagIndex as $tag => $filePaths) {
            if (empty($filePaths)) {
                continue;
            }

            $this->renderTagPage($tag, $filePaths, $application, $container);
        }
    }

    /**
     * @param array<int, string> $filePaths
     */
    private function renderTagPage(string $tag, array $filePaths, Application $application, Container $container): void
    {
        $slug = $this->sanitizeSlug($tag);
        $virtualFilePath = '__tag__:' . $slug;

        $outputDir = $container->getVariable('OUTPUT_DIR');
        if (!$outputDir) {
            throw new \RuntimeException('OUTPUT_DIR not set');
        }

        $page1OutputPath = rtrim((string) $outputDir, '/\\') . DIRECTORY_SEPARATOR
            . 'tags' . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR . 'index.html';

        $tagModel = new Tag($slug, $tag);
        foreach ($this->resolveFiles($filePaths, $container) as $tagFile) {
            $tagModel->addFile($tagFile);
        }

        $filesArray = [];
        foreach ($tagModel->files as $file) {
            $filesArray[] = [
                'title' => $file->title,
                'url' => $file->url,
                'date' => $file->date,
                'metadata' => $file->metadata,
            ];
        }

        try {
            $totalFiles = count($filesArray);
            $tagUrl = $this->deriveTagUrl($page1OutputPath, $container);
            $totalPages = $this->paginationService->totalPages($totalFiles, $this->itemsPerPage);

            for ($page = 1; $page <= $totalPages; $page++) {
                $pageFiles = $this->paginationService->sliceForPage($filesArray, $page, $this->itemsPerPage);
                $pagination = $this->paginationService->buildPagination($page, $totalPages, $tagUrl);
                $outputPath = $page === 1
                    ? $page1OutputPath
                    : $this->buildPagedOutputPath($page1OutputPath, $page);

                $enrichedMetadata = [
                    'title' => $tag,
                    'tag' => $tag,
                    'tag_slug' => $slug,
                    'tag_files' => $pageFiles,
                    'tag_files_count' => count($pageFiles),
                    'total_files' => $totalFiles,
                    'current_page' => $pagination->currentPage,
                    'total_pages' => $pagination->totalPages,
                    'pagination_prev_url' => $pagination->prevUrl,
                    'pagination_next_url' => $pagination->nextUrl,
                    'per_page' => $this->itemsPerPage,
                    'template' => 'tag-index',
                ];

                // No real file backs a tag page (no .md/.html extension on the
                // virtual path), so MarkdownRenderer/HtmlRenderer never fire for
                // it (both gate on PATHINFO_EXTENSION). The HTML must therefore
                // be rendered here, up front, and handed to renderSingleFile()
                // already populated so Application::writeOutputFile() still
                // writes it, and POST_RENDER listeners (Sitemap/Search) still
                // see a fully-formed render context.
                $renderedContent = $this->renderTemplate($enrichedMetadata, $container);

                $application->renderSingleFile($virtualFilePath, [
                    'file_metadata' => $enrichedMetadata,
                    'metadata' => $enrichedMetadata,
                    'rendered_content' => $renderedContent,
                    'output_path' => $outputPath,
                    'bypass_tag_defer' => true,
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->log('ERROR', "Failed to render tag page {$tag}: " . $e->getMessage());
            $this->logger->log('ERROR', $e->getTraceAsString());
        }
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function renderTemplate(array $metadata, Container $container): string
    {
        return $this->templateRenderer->render([
            'metadata' => $metadata,
            'content' => '',
            'title' => $metadata['title'] ?? '',
        ], $container);
    }

    /**
     * Resolves tagIndex file paths into TagFile objects by cross-referencing
     * the discovered_files metadata (title, url, date).
     *
     * @param array<int, string> $filePaths
     * @return TagFile[]
     */
    private function resolveFiles(array $filePaths, Container $container): array
    {
        /** @var array<int, array{path: string, url: string, metadata: array<string, mixed>}> $discoveredFiles */
        $discoveredFiles = $container->getVariable('discovered_files') ?? [];

        $byPath = [];
        foreach ($discoveredFiles as $fileData) {
            $byPath[$fileData['path']] = $fileData;
        }

        $tagFiles = [];
        foreach ($filePaths as $filePath) {
            if (!isset($byPath[$filePath])) {
                continue;
            }

            $fileData = $byPath[$filePath];
            $metadata = $fileData['metadata'];
            $title = $metadata['title'] ?? 'Untitled';
            $date = $this->getFileDate($metadata, $filePath);

            $tagFiles[] = new TagFile((string) $title, $fileData['url'], $date, $metadata);
        }

        return $tagFiles;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function getFileDate(array $metadata, string $filePath): string
    {
        if (isset($metadata['published_date'])) {
            return is_int($metadata['published_date'])
                ? date('Y-m-d', $metadata['published_date'])
                : (string) $metadata['published_date'];
        }

        if (isset($metadata['date'])) {
            return is_int($metadata['date']) ? date('Y-m-d', $metadata['date']) : (string) $metadata['date'];
        }

        if (file_exists($filePath)) {
            $mtime = filemtime($filePath);
            if ($mtime !== false) {
                return date('Y-m-d', $mtime);
            }
        }

        return date('Y-m-d');
    }

    /**
     * Derives the tag's page-1 URL (e.g. "/tags/{slug}/") from its page-1 output path.
     */
    private function deriveTagUrl(string $page1OutputPath, Container $container): string
    {
        $outputDir = rtrim((string) $container->getVariable('OUTPUT_DIR'), '/\\') . DIRECTORY_SEPARATOR;
        $relative = str_replace('\\', '/', substr($page1OutputPath, strlen($outputDir)));
        $relative = preg_replace('#/?index\.html$#', '/', $relative) ?? $relative;
        return '/' . ltrim($relative, '/');
    }

    /**
     * Builds the output path for page N>1: "{slugDir}/page/{n}/index.html",
     * derived from page 1's output path "{slugDir}/index.html".
     */
    private function buildPagedOutputPath(string $page1OutputPath, int $page): string
    {
        $tagDir = rtrim(dirname($page1OutputPath), '/\\');
        return $tagDir . DIRECTORY_SEPARATOR . 'page' . DIRECTORY_SEPARATOR . $page
            . DIRECTORY_SEPARATOR . 'index.html';
    }

    private function sanitizeSlug(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug ?? '', '-');
    }
}
