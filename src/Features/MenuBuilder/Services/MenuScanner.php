<?php

namespace EICC\StaticForge\Features\MenuBuilder\Services;

class MenuScanner
{
    private MenuStructureBuilder $structureBuilder;

    public function __construct(MenuStructureBuilder $structureBuilder)
    {
        $this->structureBuilder = $structureBuilder;
    }

    /**
     * @param array<int, array{path: string, url: string, metadata: array<string, mixed>}> $discoveredFiles
     * @return array<int, array<int, array{title: string, url: string, file: string, position: string}>>
     */
    public function scanFilesForMenus(array $discoveredFiles): array
    {
        $menuData = [];

        foreach ($discoveredFiles as $fileData) {
            $this->processFileForMenu($fileData, $menuData);
        }

        return $menuData;
    }

    /**
     * Process a single file to extract menu entries
     *
     * @param array{path: string, url: string, metadata: array<string, mixed>} $fileData File data from discovery
     * @param array<int, array<int|string, mixed>> $menuData Menu data structure passed by reference
     */
    private function processFileForMenu(array $fileData, array &$menuData): void
    {
        $metadata = $fileData['metadata'];

        if (isset($metadata['menu'])) {
            $menuPositions = $this->structureBuilder->parseMenuValue($metadata['menu']);

            foreach ($menuPositions as $position) {
                // Get title from metadata or extract from file
                $title = $metadata['title'] ?? $this->extractTitleFromFile($fileData['path']);

                $this->structureBuilder->addMenuEntry($position, $fileData, $title, $menuData);
            }
        }
    }

    private function extractTitleFromFile(string $filePath): string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return ucfirst(pathinfo($filePath, PATHINFO_FILENAME));
        }

        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        if ($extension === 'md') {
            // Try to get title from frontmatter
            if (preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $content, $matches)) {
                $frontmatter = $matches[1];
                if (preg_match('/^title:\s*(.+)$/m', $frontmatter, $titleMatches)) {
                    return trim($titleMatches[1]);
                }
            }

            // Fall back to first H1
            if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
                return trim($matches[1]);
            }
        } elseif ($extension === 'html') {
            // Try to get title from INI block
            if (preg_match('/<!--\s*INI\s*\n(.*?)\n-->/s', $content, $matches)) {
                $iniContent = $matches[1];
                if (preg_match('/^title:\s*(.+)$/m', $iniContent, $titleMatches)) {
                    return trim($titleMatches[1]);
                }
            }

            // Fall back to <title> tag or first <h1>
            if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $content, $matches)) {
                return trim($matches[1]);
            }
            if (preg_match('/<h1[^>]*>([^<]+)<\/h1>/i', $content, $matches)) {
                return trim(strip_tags($matches[1]));
            }
        }

        // Final fallback to filename
        return ucfirst(pathinfo($filePath, PATHINFO_FILENAME));
    }
}
