#!/usr/bin/env php
<?php

use EICC\StaticForge\Commands\InitCommand;
use EICC\StaticForge\Commands\RenderSiteCommand;
use EICC\StaticForge\Commands\UploadSiteCommand;
use EICC\StaticForge\Commands\DevServerCommand;
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
$app->add(new RenderSiteCommand($container));
$app->add(new UploadSiteCommand($container));
$app->add(new DevServerCommand());

// Run the application
$app->run();