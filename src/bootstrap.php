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
use Twig\Loader\FilesystemLoader;

// Accept optional environment path parameter
$envPath = $envPath ?? '.env';

// Look for .env in current working directory first, then fallback to package directory
$possibleEnvPaths = [
    getcwd() . '/.env',           // Current working directory
    dirname($envPath) . '/' . basename($envPath)  // Fallback to provided path
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

// If no .env found, set some sensible defaults
if (!$envLoaded) {
    $_ENV['SITE_NAME'] = $_ENV['SITE_NAME'] ?? 'StaticForge Site';
    $_ENV['SOURCE_DIR'] = $_ENV['SOURCE_DIR'] ?? 'content';
    $_ENV['TEMPLATE_DIR'] = $_ENV['TEMPLATE_DIR'] ?? 'templates';
    $_ENV['OUTPUT_DIR'] = $_ENV['OUTPUT_DIR'] ?? 'public';
    $_ENV['LOG_DIR'] = $_ENV['LOG_DIR'] ?? 'logs';
    $_ENV['DEFAULT_TEMPLATE'] = $_ENV['DEFAULT_TEMPLATE'] ?? 'staticforce';
}

// Create and configure the dependency injection container
$container = new Container();

// Set application root (computed value, not from env)
$appRoot = rtrim(dirname(__DIR__), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
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
            // Log error but don't fail - siteconfig.yaml is optional
            error_log("Warning: Failed to parse siteconfig.yaml: " . $e->getMessage());
            $siteConfig = [];
        }
        break;
    }
}

// Store site configuration in container
$container->setVariable('site_config', $siteConfig);


// Register logger as singleton service (reads from $_ENV directly)
$container->stuff('logger', function () {
    $logFile = $_ENV['LOG_FILE'] ?? 'logs/staticforge.log';
    $logLevel = $_ENV['LOG_LEVEL'] ?? 'INFO';

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
    $templateDir = $container->getVariable('TEMPLATE_DIR') ?? 'templates';
    
    $loader = new FilesystemLoader($templateDir);
    
    // Add the active template directory if set
    $templateTheme = $container->getVariable('TEMPLATE') ?? 'staticforce';
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

// Return fully configured container
return $container;
