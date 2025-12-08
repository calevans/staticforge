<?php

// Define the mapping of files to their new menu positions
// Using nested structure:
// Menu 1: Home
// Menu 2: User Guide (2.1 -> 2.1.x)
// Menu 3: Features (3.1 -> 3.1.x)
// Menu 4: Developer Guide (4.1 -> 4.1.x)

$menuMapping = [
    // Home
    'content/index.html' => '1.1',

    // User Guide (2)
    'content/guide/index.md' => '2.1',
    'content/guide/quick-start.md' => '2.1.1',
    'content/guide/configuration.md' => '2.1.2',
    'content/guide/site-config.md' => '2.1.3',
    'content/guide/commands.md' => '2.1.4',

    // Features (3)
    'content/features/index.md' => '3.1',
    // Features will be auto-assigned 3.1.x based on filename

    // Developer Guide (4)
    'content/development/index.md' => '4.1',
    'content/development/architecture.md' => '4.1.1',
    'content/development/bootstrap.md' => '4.1.2',
    'content/development/events.md' => '4.1.3',
    'content/development/features.md' => '4.1.4',
    'content/development/templates.md' => '4.1.5',
];

// Auto-discover features to ensure we don't miss any
$featureFiles = glob(__DIR__ . '/../content/features/*.md');
sort($featureFiles);
$featureCounter = 1;

foreach ($featureFiles as $file) {
    $relPath = 'content/features/' . basename($file);
    if ($relPath === 'content/features/index.md') {
        continue;
    }

    // Assign 3.1.x
    $pos = "3.1." . $featureCounter;
    $menuMapping[$relPath] = $pos;
    $featureCounter++;
}

function updateMenu($filePath, $menuValue) {
    if (!file_exists($filePath)) {
        echo "Warning: File not found: $filePath\n";
        return;
    }

    $content = file_get_contents($filePath);

    // Regex to replace menu line
    // Matches: menu: "1.1, 2.1" or menu: 1.1
    $pattern = '/^menu:\s*[\'"]?([0-9.,\s]+)[\'"]?\s*$/m';

    if (preg_match($pattern, $content)) {
        $newContent = preg_replace($pattern, "menu: '$menuValue'", $content);
        if ($content !== $newContent) {
            echo "Updating $filePath to menu: $menuValue\n";
            file_put_contents($filePath, $newContent);
        }
    } else {
        // If no menu line exists, try to add it or warn
        if (str_ends_with($filePath, '.html')) {
             $patternHtml = '/menu:\s*([0-9.,\s]+)/';
             if (preg_match($patternHtml, $content)) {
                 $newContent = preg_replace($patternHtml, "menu: $menuValue", $content);
                 echo "Updating $filePath to menu: $menuValue\n";
                 file_put_contents($filePath, $newContent);
             }
        } else {
            echo "Warning: No menu frontmatter found in $filePath\n";
        }
    }
}

foreach ($menuMapping as $relPath => $menuValue) {
    $fullPath = __DIR__ . '/../' . $relPath;
    updateMenu($fullPath, $menuValue);
}

echo "Menu update complete.\n";
