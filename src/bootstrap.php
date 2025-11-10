<?php

/**
 * Bootstrap file for StaticForge
 *
 * This file initializes the application by:
 * - Loading Composer autoloader
 * - Loading environment variables into $_ENV superglobal
 * - Setting up the dependency injection container
 * - Registering the logger service
 *
 * Environment variables remain in $_ENV and are accessed directly.
 * Only computed values (like app_root) are stored in the container.
 *
 * @param string|null $envPath Optional path to .env file (defaults to '.env')
 * @return \EICC\Utils\Container Fully configured container instance
 */

declare(strict_types=1);

// Require Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Also include user project's autoloader if we're running from a user project
$userAutoloader = getcwd() . '/vendor/autoload.php';
if (file_exists($userAutoloader) && realpath($userAutoloader) !== realpath(__DIR__ . '/../vendor/autoload.php')) {
    require_once $userAutoloader;
}

use Dotenv\Dotenv;
use EICC\Utils\Container;
use EICC\Utils\Log;

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

// Return fully configured container
return $container;
