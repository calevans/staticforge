<?php

require_once __DIR__ . '/vendor/autoload.php';

use EICC\StaticForge\Core\Application;
use EICC\Utils\Container;

$container = new Container();
// Mock logger
$logger = new class {
    public function log($level, $message) {
        // echo "[$level] $message\n";
    }
};
$container->set('logger', $logger);

// Set source dir
$container->setVariable('SOURCE_DIR', __DIR__ . '/content');
$container->setVariable('SCAN_DIRECTORIES', [__DIR__ . '/content']);

// Register extensions
$registry = new \EICC\StaticForge\Core\ExtensionRegistry();
$registry->registerExtension('md');
$registry->registerExtension('html');

// Run discovery
$discovery = new \EICC\StaticForge\Core\FileDiscovery($container, $registry);
$discovery->discoverFiles();

$files = $container->getVariable('discovered_files');

foreach ($files as $file) {
    if (strpos($file['path'], 'architecture.md') !== false) {
        echo "File: " . $file['path'] . "\n";
        echo "URL: " . $file['url'] . "\n";
        echo "Metadata: " . json_encode($file['metadata']) . "\n";
    }
}
