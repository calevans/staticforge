<?php

require_once __DIR__ . '/src/bootstrap.php';

use EICC\StaticForge\Core\FileDiscovery;

// Get the container from bootstrap
$container = require __DIR__ . '/src/bootstrap.php';

// Get FileDiscovery service
$fileDiscovery = $container->get(FileDiscovery::class);

// Manually test generateUrl logic (using reflection since it's protected)
$reflection = new ReflectionClass($fileDiscovery);
$method = $reflection->getMethod('generateUrl');
$method->setAccessible(true);

$filePath = __DIR__ . '/content/development/architecture.md';
$metadata = []; // Empty metadata for now

echo "Testing URL generation for: $filePath\n";
echo "SOURCE_DIR: " . $container->getVariable('SOURCE_DIR') . "\n";

$url = $method->invoke($fileDiscovery, $filePath, $metadata);
echo "Generated URL: $url\n";

// Also run full discovery to see what it finds
echo "\nRunning full discovery...\n";
$fileDiscovery->discoverFiles();
$files = $container->getVariable('discovered_files');

foreach ($files as $file) {
    if (strpos($file['path'], 'architecture.md') !== false) {
        echo "Found in discovery:\n";
        echo "Path: " . $file['path'] . "\n";
        echo "URL: " . $file['url'] . "\n";
    }
}
