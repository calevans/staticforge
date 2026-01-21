<?php

namespace EICC\StaticForge\Installer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\Package\PackageInterface;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    protected Composer $composer;
    protected IOInterface $io;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // No deactivation logic needed
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // No uninstall logic needed
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PackageEvents::POST_PACKAGE_INSTALL => 'onPostPackageInstall',
            PackageEvents::POST_PACKAGE_UPDATE => 'onPostPackageUpdate',
            PackageEvents::PRE_PACKAGE_UNINSTALL => 'onPrePackageUninstall',
        ];
    }

    public function onPostPackageInstall(PackageEvent $event): void
    {
        $this->handlePackageOperation($event, 'install');
    }

    public function onPostPackageUpdate(PackageEvent $event): void
    {
        $this->handlePackageOperation($event, 'update');
    }

    public function onPrePackageUninstall(PackageEvent $event): void
    {
        $this->handleUninstall($event);
    }

    /**
     * Handle install and update operations
     */
    protected function handlePackageOperation(PackageEvent $event, string $operation): void
    {
        /** @var PackageInterface $package */
        $package = $operation === 'update' 
            ? $event->getOperation()->getTargetPackage() // @phpstan-ignore-line
            : $event->getOperation()->getPackage();      // @phpstan-ignore-line

        if (!$this->isStaticForgeTheme($package)) {
            return;
        }

        $themeName = $this->getThemeName($package);
        $sourcePath = $this->getInstallPath($package);
        
        // Look for templates directory within the package
        // Metadata in composer.json extra.staticforge.theme.source can override default 'templates'
        $extra = $package->getExtra();
        $relSource = $extra['staticforge']['theme']['source'] ?? 'templates';
        
        $fullSourcePath = $sourcePath . '/' . $relSource;
        $targetPath = getcwd() . '/templates/' . $themeName;

        if (!is_dir($fullSourcePath)) {
            $this->io->writeError("<warning>StaticForge: Theme source directory not found: {$fullSourcePath}</warning>");
            return;
        }

        // Safety check: Do not overwrite if directory exists
        if (is_dir($targetPath)) {
            $this->io->write("<info>StaticForge: Theme directory 'templates/{$themeName}' already exists. Skipping copy.</info>");
            return;
        }

        $this->io->write("<info>StaticForge: Installing theme '{$themeName}'...</info>");
        $this->recursiveCopy($fullSourcePath, $targetPath);
        $this->io->write("<info>StaticForge: Theme installed to 'templates/{$themeName}'</info>");
    }

    /**
     * Handle uninstall operation
     */
    protected function handleUninstall(PackageEvent $event): void
    {
        /** @var PackageInterface $package */
        $package = $event->getOperation()->getPackage(); // @phpstan-ignore-line

        if (!$this->isStaticForgeTheme($package)) {
            return;
        }

        $themeName = $this->getThemeName($package);
        $targetPath = getcwd() . '/templates/' . $themeName;

        if (!is_dir($targetPath)) {
            return;
        }

        // Check for interactivity or force flag
        // If non-interactive (CI/CD or -n), we delete automatically as requested
        if (!$this->io->isInteractive()) {
            $this->io->write("<info>StaticForge: Auto-removing theme directory 'templates/{$themeName}'</info>");
            $this->recursiveRemove($targetPath);
            return;
        }

        // Interactive: Ask user
        if ($this->io->askConfirmation("<question>StaticForge: Delete theme directory 'templates/{$themeName}'? [y/N]</question> ", false)) {
            $this->recursiveRemove($targetPath);
            $this->io->write("<info>StaticForge: Removed 'templates/{$themeName}'</info>");
        } else {
            $this->io->write("<info>StaticForge: Kept 'templates/{$themeName}'</info>");
        }
    }

    /**
     * Check if package is a StaticForge theme
     */
    protected function isStaticForgeTheme(PackageInterface $package): bool
    {
        // Check by type
        if ($package->getType() === 'staticforge-theme') {
            return true;
        }

        // Check by extra metadata
        $extra = $package->getExtra();
        if (isset($extra['staticforge']['theme'])) {
            return true;
        }

        return false;
    }

    /**
     * Get theme name from metadata or package name
     */
    protected function getThemeName(PackageInterface $package): string
    {
        $extra = $package->getExtra();
        if (isset($extra['staticforge']['theme']['name'])) {
            return $extra['staticforge']['theme']['name'];
        }

        // Fallback: use package name (vendor/name -> name)
        $parts = explode('/', $package->getName());
        return end($parts);
    }

    protected function getInstallPath(PackageInterface $package): string
    {
        return $this->composer->getInstallationManager()->getInstallPath($package);
    }

    protected function recursiveCopy(string $src, string $dst): void
    {
        $dir = opendir($src);
        @mkdir($dst, 0755, true);
        
        while (($file = readdir($dir)) !== false) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    $this->recursiveCopy($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    protected function recursiveRemove(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->recursiveRemove("$dir/$file") : unlink("$dir/$file");
        }
        return; // rmdir($dir); // Optional: keep the empty dir? No, usually delete.
        rmdir($dir);
    }
}
