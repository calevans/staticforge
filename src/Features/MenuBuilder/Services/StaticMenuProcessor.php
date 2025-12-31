<?php

namespace EICC\StaticForge\Features\MenuBuilder\Services;

use EICC\Utils\Container;
use EICC\Utils\Log;

class StaticMenuProcessor
{
    private MenuHtmlGenerator $htmlGenerator;
    private Log $logger;

    public function __construct(MenuHtmlGenerator $htmlGenerator, Log $logger)
    {
        $this->htmlGenerator = $htmlGenerator;
        $this->logger = $logger;
    }

    /**
     * Process static menus from siteconfig.yaml
     *
     * Reads menu definitions from site configuration and generates HTML
     * for named menus (e.g., 'top', 'footer'). These are stored in the
     * container as menu_{name} variables.
     */
    public function processStaticMenus(Container $container): void
    {
        $siteConfig = $container->getVariable('site_config');
        
        $baseUrl = null;
        // Prefer SITE_BASE_URL (uppercase) as it is the standard env var name
        // and what UploadSiteCommand sets.
        if ($container->hasVariable('SITE_BASE_URL')) {
            $baseUrl = $container->getVariable('SITE_BASE_URL');
        } elseif ($container->hasVariable('site_base_url')) {
            $baseUrl = $container->getVariable('site_base_url');
        }

        if ($baseUrl === null) {
            throw new \RuntimeException('SITE_BASE_URL not set in container');
        }

        // Check if we have menu configuration
        if (!is_array($siteConfig) || !isset($siteConfig['menu']) || !is_array($siteConfig['menu'])) {
            return;
        }

        $menus = $siteConfig['menu'];

        foreach ($menus as $menuName => $menuItems) {
            if (!is_array($menuItems)) {
                $this->logger->log('WARNING', "Menu '{$menuName}' in siteconfig.yaml is not an array, skipping");
                continue;
            }

            // Convert simple key/value pairs to menu item structure
            // Using 'direct' key format expected by generateMenuHtml()
            $items = ['direct' => []];
            foreach ($menuItems as $title => $url) {
                $url = (string)$url;

                // Prepend base URL if it's a relative path (starts with /) and not an absolute URL
                // Also check if it doesn't already start with the base URL to avoid double prefixing
                if ($baseUrl && str_starts_with($url, '/') && !str_starts_with($url, '//')) {
                    $cleanBaseUrl = rtrim($baseUrl, '/');
                    // Only prepend if the URL doesn't already start with the base URL path
                    // This prevents issues if someone manually added the base path in config
                    if ($cleanBaseUrl !== '' && !str_starts_with($url, $cleanBaseUrl . '/')) {
                        $url = $cleanBaseUrl . $url;
                    }
                }

                $items['direct'][] = [
                    'title' => (string)$title,
                    // Do not strip leading slash - allow absolute paths like "/"
                    'url' => $url,
                    'file' => '', // Static menu items have no associated file
                    'position' => '' // Position is determined by YAML order
                ];
            }

            // Generate HTML using existing menu HTML generator
            $html = $this->htmlGenerator->generateMenuHtml($items, 0);

            // Store in container as menu_{name}
            $varName = "menu_{$menuName}";
            if ($container->hasVariable($varName)) {
                $container->updateVariable($varName, $html);
            } else {
                $container->setVariable($varName, $html);
            }

            $this->logger->log(
                'INFO',
                "MenuBuilder: Generated static menu '{$menuName}' with " .
                count($items['direct']) . ' items'
            );
        }
    }
}
