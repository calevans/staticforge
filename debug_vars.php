<?php

require_once __DIR__ . '/src/bootstrap.php';

use EICC\StaticForge\Services\TemplateVariableBuilder;
use EICC\Utils\Container;

// Get container
$container = require __DIR__ . '/src/bootstrap.php';

// Check if SITE_BASE_URL is in container
echo "Checking container for SITE_BASE_URL...\n";
$val = $container->getVariable('SITE_BASE_URL');
echo "SITE_BASE_URL: " . ($val ?? 'NULL') . "\n";

// Instantiate builder
$builder = new TemplateVariableBuilder();

// Build variables
$vars = $builder->build([], $container, 'test.md');

echo "Checking built variables for site_base_url...\n";
if (isset($vars['site_base_url'])) {
    echo "site_base_url: " . $vars['site_base_url'] . "\n";
} else {
    echo "site_base_url is MISSING\n";
}
