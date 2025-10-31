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

use Dotenv\Dotenv;
use EICC\Utils\Container;
use EICC\Utils\Log;

// Accept optional environment path parameter
$envPath = $envPath ?? '.env';

// Load environment variables
$dotenv = Dotenv::createUnsafeImmutable(dirname($envPath), basename($envPath));
$dotenv->load();

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
