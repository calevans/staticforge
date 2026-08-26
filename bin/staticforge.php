#!/usr/bin/env php
<?php

// Load Composer autoloader first - handle both dev and library installation
$autoloaderPaths = [
    __DIR__ . '/../vendor/autoload.php',           // Development mode
    __DIR__ . '/../autoload.php',                  // When in vendor/bin/ (points to vendor/autoload.php)
    getcwd() . '/vendor/autoload.php'              // Fallback to current working directory
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
    echo "Error: Could not find Composer autoloader. Please run 'composer install'.\n";
    exit(1);
}

// Check for required extensions
$requiredExtensions = ['xml', 'mbstring'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

if (!empty($missingExtensions)) {
    echo "Error: The following required PHP extensions are missing: " . implode(', ', $missingExtensions) . ".\n";
    echo "Please install them and try again.\n";
    exit(1);
}

use EICC\StaticForge\Commands\InitCommand;
use EICC\StaticForge\Commands\Audit\ConfigCommand;
use EICC\StaticForge\Commands\Audit\ContentCommand;
use EICC\StaticForge\Commands\Audit\LinksCommand;
use EICC\StaticForge\Commands\Audit\LiveCommand;
use EICC\StaticForge\Commands\Audit\SeoCommand;
use EICC\StaticForge\Commands\Make\ContentCreatorCommand;
use EICC\StaticForge\Commands\Make\HtaccessCommand;
use EICC\StaticForge\Core\Events\ConsoleInitEvent;
use EICC\StaticForge\Core\FeatureManager;
use EICC\StaticForge\Core\EventManager;
use Symfony\Component\Console\Application;

// Bootstrap application - handle both dev and vendor/bin locations
$bootstrapPath = __DIR__ . '/../src/bootstrap.php';
if (!file_exists($bootstrapPath)) {
    // When installed via Composer, we're in vendor/bin/
    $bootstrapPath = __DIR__ . '/../eicc/staticforge/src/bootstrap.php';
}
$container = require $bootstrapPath;

// Create console application
$app = new Application('StaticForge', '3.1.2');

// Add commands
$app->addCommand(new InitCommand());
$app->addCommand(new ConfigCommand($container));
$app->addCommand(new ContentCommand($container));
$app->addCommand(new ContentCreatorCommand($container));
$app->addCommand(new LinksCommand($container));
$app->addCommand(new LiveCommand($container));
$app->addCommand(new SeoCommand($container));
$app->addCommand(new HtaccessCommand($container));

// Load features
$container->get(FeatureManager::class)->loadFeatures();

// Dispatch CONSOLE_INIT event to allow features to register commands
$container->get(EventManager::class)->fire('CONSOLE_INIT', new ConsoleInitEvent('CONSOLE_INIT', $app));

// Run the application
$app->run();
