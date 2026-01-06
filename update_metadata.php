<?php

$baseUrl = "https://calevans.com/staticforge";
$groups = [
    'development' => 'development',
    'features' => 'features',
    'guide' => 'guide'
];

foreach ($groups as $dir => $urlPrefix) {
    $files = glob("content/$dir/*.md");
    foreach ($files as $file) {
        $content = file_get_contents($file);
        // Match frontmatter block
        if (preg_match('/^---\s*\n(.*?)\n---/s', $content, $matches)) {
            $frontmatter = $matches[1];
            // Check if url already exists
            if (strpos($frontmatter, 'url:') !== false) {
                continue;
            }
            
            $filename = basename($file, '.md');
            $url = "{$baseUrl}/{$urlPrefix}/{$filename}.html";
            
            $newFrontmatter = $frontmatter . "\nurl: \"{$url}\"";
            $newContent = str_replace($matches[1], $newFrontmatter, $content);
            
            file_put_contents($file, $newContent);
            echo "Updated $file\n";
        }
    }
}
