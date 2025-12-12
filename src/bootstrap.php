<?php

/**
 * Bootstrap file for StaticForge
 *
 * This file initializes the application by:
 * - Loading Composer autoloader
 * - Loading environment variables into $_ENV superglobal
 * - Loading optional siteconfig.yaml for site-wide configuration
 * - Setting up the dependency injection container
 * - Registering the logger service
 *
 * Environment variables remain in $_ENV and are accessed directly.
 * Only computed values (like app_root) are stored in the container.
 * Site configuration from siteconfig.yaml is stored in container as 'site_config'.
 *
 * @param string|null $envPath Optional path to .env file (defaults to '.env')
 * @return \EICC\Utils\Container Fully configured container instance
 */

declare(strict_types=1);

// Require Composer autoloader - handle both dev and library installation
// Check if autoloader is already loaded (e.g., by the binary script)
if (!class_exists('Composer\Autoload\ClassLoader')) {
    $autoloaderPaths = [
        __DIR__ . '/../vendor/autoload.php',
        __DIR__ . '/../../../autoload.php',
        getcwd() . '/vendor/autoload.php'
    ];

    $autoloaderLoaded = false;
    foreach ($autoloaderPaths as $autoloaderPath) {
        if (file_exists($autoloaderPath)) {
            require_once $autoloaderPath;
            $autoloaderLoaded = true;
            break;
        }
    }

    if (!$autoloaderLoaded) {
        throw new \RuntimeException('Could not find Composer autoloader. Please run "composer install".');
    }
}

use Dotenv\Dotenv;
use EICC\Utils\Container;
use EICC\Utils\Log;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\FeatureManager;
use EICC\StaticForge\Core\ExtensionRegistry;
use EICC\StaticForge\Core\FileDiscovery;
use EICC\StaticForge\Core\FileProcessor;
use EICC\StaticForge\Core\ErrorHandler;
use Twig\Loader\FilesystemLoader;

// Accept optional environment path parameter
$envPath = $envPath ?? '.env';

// Look for .env in current working directory first, then fallback to package directory
$possibleEnvPaths = [
    dirname($envPath) . '/' . basename($envPath),  // Provided path (or default .env)
    getcwd() . '/.env'           // Current working directory fallback
];

$envLoaded = false;
foreach ($possibleEnvPaths as $path) {
    if (file_exists($path)) {
        $dotenv = Dotenv::createUnsafeImmutable(dirname($path), basename($path));
        $dotenv->load();
        $envLoaded = true;
        break;
    }
}

// Calculate appRoot by traversing up from current directory to find composer.json or .env
$searchPath = getcwd();
$appRoot = null;
while ($searchPath !== '/' && $searchPath !== '.' && $searchPath !== false) {
    if (file_exists($searchPath . '/composer.json') || file_exists($searchPath . '/.env')) {
        $appRoot = $searchPath . '/';
        break;
    }
    $searchPath = dirname($searchPath);
}

// Fallback: if not found, assume current directory
if (!$appRoot) {
    $appRoot = getcwd() . '/';
}

// Helper to normalize paths to absolute paths
$normalizePath = function ($path) use ($appRoot) {
    if (!$path) {
        return $path;
    }
    // If path starts with / (linux) or X:\ (windows), it's absolute
    if (strpos($path, '/') === 0 || preg_match('/^[a-zA-Z]:\\\\/', $path)) {
        return $path;
    }
    // If path contains :// it is likely a stream wrapper (vfs://, file://, etc)
    if (strpos($path, '://') !== false) {
        return $path;
    }
    return $appRoot . $path;
};

// Ensure sensible defaults are set if not provided in .env or environment
// Normalize all paths to be absolute based on appRoot
$_ENV['SOURCE_DIR'] = $normalizePath($_ENV['SOURCE_DIR'] ?? 'content');
$_ENV['TEMPLATE_DIR'] = $normalizePath($_ENV['TEMPLATE_DIR'] ?? 'templates');
$_ENV['OUTPUT_DIR'] = $normalizePath($_ENV['OUTPUT_DIR'] ?? 'public');
$_ENV['LOG_DIR'] = $normalizePath($_ENV['LOG_DIR'] ?? 'logs');

// LOG_FILE might be relative or absolute. If default, construct from LOG_DIR.
if (!isset($_ENV['LOG_FILE'])) {
    $_ENV['LOG_FILE'] = $_ENV['LOG_DIR'] . '/staticforge.log';
} else {
    $_ENV['LOG_FILE'] = $normalizePath($_ENV['LOG_FILE']);
}

$_ENV['LOG_LEVEL'] = $_ENV['LOG_LEVEL'] ?? 'INFO';
$_ENV['TEMPLATE'] = $_ENV['TEMPLATE'] ?? 'staticforce';

// Create and configure the dependency injection container
$container = new Container();
$container->setVariable('app_root', $appRoot);

// Copy environment variables to container for backward compatibility
// Components read configuration from container variables
foreach ($_ENV as $key => $value) {
    $container->setVariable($key, $value);
}

// Load optional siteconfig.yaml file
// This file contains non-sensitive site-wide configuration (menus, site title, etc.)
// Unlike .env, this file can be committed to version control
$siteConfigPaths = [
    getcwd() . '/siteconfig.yaml',           // Current working directory
    $appRoot . 'siteconfig.yaml'             // Application root
];

$siteConfig = [];
foreach ($siteConfigPaths as $configPath) {
    if (file_exists($configPath)) {
        try {
            $siteConfig = Yaml::parseFile($configPath);
            if (!is_array($siteConfig)) {
                $siteConfig = [];
            }
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Critical Error: Failed to parse siteconfig.yaml at {$configPath}: " . $e->getMessage(),
                0,
                $e
            );
        }
        break;
    }
}

// Store site configuration in container
$container->setVariable('site_config', $siteConfig);

// Override TEMPLATE from siteconfig if present
if (isset($siteConfig['site']['template'])) {
    $container->setVariable('TEMPLATE', $siteConfig['site']['template']);
}



// Register logger as singleton service (reads from $_ENV directly)
$container->stuff('logger', function () {
    $logFile = $_ENV['LOG_FILE'];
    $logLevel = $_ENV['LOG_LEVEL'];

    // Ensure logs directory exists
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        if (!mkdir($logDir, 0755, true) && !is_dir($logDir)) {
            throw new RuntimeException("Cannot create log directory: {$logDir}");
        }
    }

    // Create and return logger instance
    return new Log('staticforge', $logFile, $logLevel);
});

// Register Twig as a shared service
$container->stuff('twig', function () use ($container) {
    $templateDir = $container->getVariable('TEMPLATE_DIR');

    // Ensure template directory exists to prevent crashes in tests or fresh installs
    if (!is_dir($templateDir)) {
        if (!mkdir($templateDir, 0755, true) && !is_dir($templateDir)) {
            throw new RuntimeException("Cannot create template directory: {$templateDir}");
        }
    }

    $loader = new FilesystemLoader($templateDir);

    // Add the active template directory if set
    $templateTheme = $container->getVariable('TEMPLATE');
    if (is_dir($templateDir . '/' . $templateTheme)) {
        $loader->addPath($templateDir . '/' . $templateTheme);
    }

    return new Environment($loader, [
        'debug' => true,
        'strict_variables' => false,
        'autoescape' => 'html',
        'cache' => false,
    ]);
});

// Register Core Services
$eventManager = new EventManager($container);
$container->add(EventManager::class, $eventManager);

$featureManager = new FeatureManager($container, $eventManager);
$container->add(FeatureManager::class, $featureManager);

$extensionRegistry = new ExtensionRegistry($container);
$container->add(ExtensionRegistry::class, $extensionRegistry);

$fileDiscovery = new FileDiscovery($container, $extensionRegistry);
$container->add(FileDiscovery::class, $fileDiscovery);

$errorHandler = new ErrorHandler($container);
$container->add(ErrorHandler::class, $errorHandler);

$fileProcessor = new FileProcessor($container, $eventManager);
$container->add(FileProcessor::class, $fileProcessor);

// Return fully configured container
return $container;
