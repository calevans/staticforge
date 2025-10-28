#!/usr/bin/env php
<?php

use EICC\StaticForge\Commands\RenderSiteCommand;
use Symfony\Component\Console\Application;

// Include Composer autoloader
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    echo "Composer autoloader not found. Please run 'composer install' first.\n";
    exit(1);
}
require $autoload;

// Create console application
$app = new Application('StaticForge', '1.0.0');

// Add commands
$app->add(new RenderSiteCommand());

// Run the application
$app->run();