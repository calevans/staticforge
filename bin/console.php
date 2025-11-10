#!/usr/bin/env php
<?php

use EICC\StaticForge\Commands\InitCommand;
use EICC\StaticForge\Commands\RenderSiteCommand;
use EICC\StaticForge\Commands\UploadSiteCommand;
use EICC\StaticForge\Commands\DevServerCommand;
use Symfony\Component\Console\Application;

// Bootstrap application
$container = require __DIR__ . '/../src/bootstrap.php';

// Create console application
$app = new Application('StaticForge', '1.0.0');

// Add commands
$app->add(new InitCommand());
$app->add(new RenderSiteCommand($container));
$app->add(new UploadSiteCommand($container));
$app->add(new DevServerCommand());

// Run the application
$app->run();