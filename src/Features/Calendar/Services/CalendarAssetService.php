<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\Calendar\Services;

use EICC\Utils\Container;
use EICC\Utils\Log;

class CalendarAssetService
{
    private Log $logger;
    private string $projectRoot;

    public function __construct(Log $logger, string $projectRoot = '')
    {
        $this->logger = $logger;
        // If projectRoot is empty, assume we are in src/Features... and go up 4 levels
        $this->projectRoot = $projectRoot ?: dirname(__DIR__, 4);
    }

    public function copyAssets(Container $container): void
    {
        $outputDir = $container->getVariable('OUTPUT_DIR');
        
        // If OUTPUT_DIR is not set, we might be in a dev server context or initialization.
        // If it is strictly for static build, we need it. 
        // However, for copying the template to the user's `templates` dir, we need the project root.
        // We will handle assets (public) and templates (source) separately.

        // 1. Copy public assets (JS/CSS)
        if ($outputDir) {
            $this->copyPublicAssets($outputDir);
        } else {
             // Try to find public dir relative to root if OUTPUT_DIR missing (e.g. dev mode)
             $publicDir = $this->projectRoot . '/public';
             if (is_dir($publicDir)) {
                 $this->copyPublicAssets($publicDir);
             } else {
                 $this->logger->log('WARNING', 'Could not determine output directory for Calendar assets.');
             }
        }

        // 2. Copy Default Template to User Project
        $this->copyTemplate();
    }

    private function copyPublicAssets(string $destinationRoot): void
    {
        $sourceDir = dirname(__DIR__) . '/Assets';
        $destDirJs = $destinationRoot . '/assets/js';
        $destDirCss = $destinationRoot . '/assets/css';

        // Create directories
        if (!is_dir($destDirJs)) mkdir($destDirJs, 0755, true);
        if (!is_dir($destDirCss)) mkdir($destDirCss, 0755, true);

        // Copy JS
        $jsFile = 'calendar.js';
        if (copy($sourceDir . '/js/' . $jsFile, $destDirJs . '/' . $jsFile)) {
            $this->logger->log('INFO', "Copied Calendar asset: $jsFile");
        } else {
            $this->logger->log('ERROR', "Failed to copy Calendar asset: $jsFile");
        }

        // Copy CSS
        $cssFile = 'calendar.css';
        if (copy($sourceDir . '/css/' . $cssFile, $destDirCss . '/' . $cssFile)) {
            $this->logger->log('INFO', "Copied Calendar asset: $cssFile");
        } else {
            $this->logger->log('ERROR', "Failed to copy Calendar asset: $cssFile");
        }
    }

    private function copyTemplate(): void
    {
        $sourceTemplate = dirname(__DIR__) . '/Templates/calendar-wrapper.twig';
        $destTemplateDir = $this->projectRoot . '/templates/shortcodes';
        $destTemplate = $destTemplateDir . '/calendar-wrapper.twig';

        if (!is_dir($destTemplateDir)) {
            mkdir($destTemplateDir, 0755, true);
        }

        // Only copy if it doesn't exist (don't overwrite user customizations)
        if (!file_exists($destTemplate)) {
            if (copy($sourceTemplate, $destTemplate)) {
                $this->logger->log('INFO', "Copied default Calendar template to: templates/shortcodes/calendar-wrapper.twig");
            } else {
                $this->logger->log('ERROR', "Failed to copy default Calendar template.");
            }
        } else {
            $this->logger->log('DEBUG', "Calendar template already exists in templates/shortcodes, skipping copy.");
        }
    }
}
