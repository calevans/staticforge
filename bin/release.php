#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Release Helper Script
 *
 * Updates composer.json version, commits, tags, and pushes.
 * Usage: lando php bin/release.php <version>
 */

if ($argc !== 2) {
    echo "Usage: lando php bin/release.php <version>\n";
    exit(1);
}

$version = $argv[1];

// Validate version format (X.Y.Z)
if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
    echo "Error: Version must be in format X.Y.Z (e.g., 1.15.0)\n";
    exit(1);
}

$composerFile = __DIR__ . '/../composer.json';
if (!file_exists($composerFile)) {
    echo "Error: composer.json not found\n";
    exit(1);
}

// Update composer.json
$content = file_get_contents($composerFile);
$json = json_decode($content, true);
$oldVersion = $json['version'] ?? 'unknown';

if ($oldVersion === $version) {
    echo "ℹ️  Version is already set to $version in composer.json.\n";
} else {
    $json['version'] = $version;
    // JSON_PRETTY_PRINT uses 4 spaces which matches the project style
    $newContent = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $newContent .= "\n";

    if (file_put_contents($composerFile, $newContent) === false) {
        echo "❌ Failed to write to composer.json\n";
        exit(1);
    }
    echo "✅ Updated composer.json version from {$oldVersion} to {$version}\n";
}

// Helper to run commands
function runCommand(string $cmd): void {
    echo "> $cmd\n";
    passthru($cmd, $returnVar);
    if ($returnVar !== 0) {
        echo "❌ Command failed: $cmd\n";
        exit(1);
    }
}

echo "\nStarting git operations...\n";

// 1. Add the file
runCommand("git add composer.json");

// 2. Commit (only if there are changes)
exec("git diff --cached --quiet", $output, $diffStatus);
if ($diffStatus === 1) {
    runCommand("git commit -m \"Bump version to {$version}\"");
} else {
    echo "ℹ️  No changes to commit (composer.json was already up to date).\n";
}

// 3. Tag
exec("git tag -l {$version}", $tags);
if (in_array($version, $tags)) {
    echo "ℹ️  Tag $version already exists.\n";
} else {
    runCommand("git tag {$version}");
}

// 4. Push
echo "\nPushing to remote...\n";
runCommand("git push origin HEAD");
runCommand("git push origin {$version}");

echo "\n🎉 Release $version completed successfully!\n";
