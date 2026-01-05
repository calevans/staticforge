<?php

declare(strict_types=1);

namespace EICC\StaticForge\Core;

class AssetManager
{
    /**
     * @var array<string, array{src: string, deps: array<string>, inFooter: bool}>
     */
    private array $scripts = [];

    /**
     * @var array<string, array{src: string, deps: array<string>}>
     */
    private array $styles = [];

    /**
     * Add a script to the queue.
     *
     * @param string $handle Unique handle for the script
     * @param string $src Source URL/Path
     * @param array<string> $deps Array of handles this script depends on
     * @param bool $inFooter Whether to output in footer (true) or head (false)
     */
    public function addScript(string $handle, string $src, array $deps = [], bool $inFooter = true): void
    {
        $this->scripts[$handle] = [
            'src' => $src,
            'deps' => $deps,
            'inFooter' => $inFooter,
        ];
    }

    /**
     * Add a style to the queue.
     *
     * @param string $handle Unique handle for the style
     * @param string $src Source URL/Path
     * @param array<string> $deps Array of handles this style depends on
     */
    public function addStyle(string $handle, string $src, array $deps = []): void
    {
        $this->styles[$handle] = [
            'src' => $src,
            'deps' => $deps,
        ];
    }

    /**
     * Get the HTML for scripts.
     *
     * @param bool $inFooter Get scripts for footer (true) or head (false)
     * @return string
     */
    public function getScripts(bool $inFooter = true): string
    {
        $sorted = $this->resolveDependencies($this->scripts);
        $html = '';
        foreach ($sorted as $handle) {
            $script = $this->scripts[$handle];
            if ($script['inFooter'] === $inFooter) {
                $html .= sprintf('<script src="%s"></script>' . PHP_EOL, $script['src']);
            }
        }
        return $html;
    }

    /**
     * Get the HTML for styles.
     *
     * @return string
     */
    public function getStyles(): string
    {
        $sorted = $this->resolveDependencies($this->styles);
        $html = '';
        foreach ($sorted as $handle) {
            $style = $this->styles[$handle];
            $html .= sprintf('<link rel="stylesheet" href="%s">' . PHP_EOL, $style['src']);
        }
        return $html;
    }

    /**
     * Resolve dependencies using topological sort.
     *
     * @param array<string, mixed> $items
     * @return array<string> Sorted handles
     */
    private function resolveDependencies(array $items): array
    {
        $resolved = [];
        $seen = [];
        $visiting = [];

        $resolve = function ($handle) use (&$resolved, &$seen, &$visiting, &$resolve, $items) {
            if (isset($seen[$handle])) {
                return;
            }
            if (isset($visiting[$handle])) {
                throw new \RuntimeException("Circular dependency detected for asset: {$handle}");
            }

            $visiting[$handle] = true;

            if (isset($items[$handle])) {
                foreach ($items[$handle]['deps'] as $dep) {
                    if (isset($items[$dep])) {
                        $resolve($dep);
                    }
                }
            }

            unset($visiting[$handle]);
            $seen[$handle] = true;
            $resolved[] = $handle;
        };

        foreach (array_keys($items) as $handle) {
            $resolve($handle);
        }

        return $resolved;
    }
}
