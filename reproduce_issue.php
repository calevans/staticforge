<?php

require_once __DIR__ . '/vendor/autoload.php';

use EICC\StaticForge\Features\MenuBuilder\Services\MenuHtmlGenerator;

$generator = new MenuHtmlGenerator();

// Simulate the data structure created by MenuStructureBuilder for:
// menu: 2.1 (User Guide)
// menu: 2.1.1 (Quick Start)
$menuData = [
    1 => [
        'title' => 'User Guide',
        'url' => '/guide/index.html',
        'file' => 'content/guide/index.md',
        'position' => '2.1',
        // Child item
        1 => [
            'title' => 'Quick Start',
            'url' => '/guide/quick-start.html',
            'file' => 'content/guide/quick-start.md',
            'position' => '2.1.1'
        ]
    ]
];

echo "Generating HTML for Menu 2...\n";
$html = $generator->generateMenuHtml($menuData, 2);

echo "Output HTML:\n";
echo $html;

echo "\nAnalysis:\n";
if (strpos($html, 'Quick Start') === false) {
    echo "FAIL: 'Quick Start' (child item) is missing from the output.\n";
    echo "Reason: The generator treated it as a flat list and ignored the children of item 1.\n";
} else {
    echo "SUCCESS: 'Quick Start' is present.\n";
}
