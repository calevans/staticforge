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
use EICC\StaticForge\Commands\CheckCommand;
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
$app = new Application('StaticForge', '1.0.0');

// Add commands
$app->add(new InitCommand());
$app->add(new CheckCommand($container));

// Load features
$container->get(FeatureManager::class)->loadFeatures();

// Dispatch CONSOLE_INIT event to allow features to register commands
$container->get(EventManager::class)->fire('CONSOLE_INIT', ['application' => $app]);

// Run the application
$app->run();