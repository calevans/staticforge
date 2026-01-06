<?php

$baseUrl = "https://calevans.com/staticforge";

$files = [];

// Helper to add group
function addGroup($dir, $urlPrefix, &$files, $baseUrl) {
    if (!is_dir("content/$dir")) return;
    foreach (glob("content/$dir/*.md") as $file) {
        $name = basename($file, '.md');
        $files[$file] = "{$baseUrl}/{$urlPrefix}/{$name}.html";
    }
}

// Standard groups
addGroup('development', 'development', $files, $baseUrl);
addGroup('features', 'features', $files, $baseUrl);
addGroup('guide', 'guide', $files, $baseUrl);

// Examples (Explicit mapping based on public/ structure)
$examples = [
    'content/examples/landing-page.html' => 'examples/landing-page.html', // HTML file!
    'content/examples/simple-page.md' => 'examples/simple-page.html',
    'content/examples/documentation-page.md' => 'examples/documentation/documentation-page.html',
    'content/examples/portfolio-item.md' => 'examples/portfolio/portfolio-item.html',
    'content/examples/blog-post.md' => 'examples/tutorials/blog-post.html',
    'content/examples/rss-enabled-article.md' => 'examples/tutorials/rss-enabled-article.html',
    'content/examples/shortcodes.md' => 'examples/docs-examples/shortcodes.html',
    'content/examples/README.md' => 'examples/README.html',
];

foreach ($examples as $path => $relUrl) {
    $files[$path] = "{$baseUrl}/{$relUrl}";
}

foreach ($files as $filePath => $url) {
    if (!file_exists($filePath)) {
        echo "Skipping missing file: $filePath\n";
        continue;
    }

    $content = file_get_contents($filePath);

    // 1. Remove ANY existing 'url: "..."' lines to clean up previous mess
    $lines = explode("\n", $content);
    $newLines = [];
    $inFrontmatter = false;
    $frontmatterEndIndex = -1;

    // Check if file starts with frontmatter
    $hasFrontmatter = false;
    // Check for YAML (---) or HTML comment (<!--)
    // Actually, let's look for the block.
    // For HTML, frontmatter is inside <!-- ... --> ?
    // landing-page.html has <!-- \n --- ... --- \n -->

    // Simple approach: using regex to identifying the block
    // Then modifying the block.

    // But first, let's strip OLD url lines using regex replacement on the whole string
    // This removes them from everywhere (including inside content if unlucky, but 'url: "http...' is specific)
    // We match `url: "http..."` at start of line
    $content = preg_replace('/^url: "https:\/\/calevans\.com\/staticforge.*?"\s*$/m', '', $content);
    // Remove empty lines created?
    // Let's not worry about empty lines for now.

    // 2. Re-insert correct URL
    if (str_ends_with($filePath, '.html')) {
        // HTML often wraps yaml in comments.
        // Look for existing `url:` inside metadata? No we removed it.
        // Insert before standard `---` closer.
        $pattern = '/^---(\s*[\s\S]*?)\n---/m';
        // Note: HTML files might have <!-- --- ... --- -->
        // My regex `^---` might fail if it's indented or inside comment?
        // Let's check `landing-page.html` content style.
        // It has `<!--` then `---`.

        if (preg_match('/(<!--\s*\n)?---\n([\s\S]*?)\n---/s', $content, $matches)) {
            // $matches[0] is the whole block including fences
             // Replace the closing `---` with `url: ... \n---`
             // Ensure we don't double insert if I failed to strip it (I stripped it globally)

             // We need to be careful with the replacement.
             // We can just append to the content of the block.

             $blockContent = $matches[2];
             $newBlockContent = rtrim($blockContent) . "\nurl: \"$url\"";
             $newBlock = str_replace($blockContent, $newBlockContent, $matches[0]);

             $content = str_replace($matches[0], $newBlock, $content);
        }
    } else {
        // Markdown
        // Find the FIRST `---` block
        if (preg_match('/^---\s*\n([\s\S]*?)\n---/s', $content, $matches)) {
             $blockContent = $matches[1];
             $newBlockContent = rtrim($blockContent) . "\nurl: \"$url\"";
             // Reconstruct the block
             $newBlock = "---\n$newBlockContent\n---";
             $content = str_replace($matches[0], $newBlock, $content);
        }
    }

    file_put_contents($filePath, $content);
    echo "Fixed $filePath\n";
}
