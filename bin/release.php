#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Release Helper Script
 *
 * Generates a changelog from git history, tags the release, and pushes.
 * Version is tracked by git tags only — composer.json has no "version"
 * field. Composer resolves the installed version from the tag itself;
 * a hardcoded "version" field that drifts out of sync with the tag is
 * silently dropped by Packagist (it can't tell which one is right).
 * Usage: lando php bin/release.php <version>
 */

$currentDir = getcwd();

// Validate Git Repository
if (!is_dir($currentDir . '/.git')) {
    echo "Error: Current directory is not a git repository.\n";
    exit(1);
}

$composerFile = $currentDir . '/composer.json';

// Validate composer.json exists
if (!file_exists($composerFile)) {
    echo "Error: composer.json not found in current directory.\n";
    exit(1);
}

$content = file_get_contents($composerFile);
$json = json_decode($content, true);

// Validate Vendor
$packageName = $json['name'] ?? '';
if (!str_starts_with($packageName, 'eicc/') && !str_starts_with($packageName, 'calevans/')) {
    echo "Error: This script is restricted to 'eicc' or 'calevans' packages.\n";
    exit(1);
}

if ($argc !== 2) {
    // Get tags
    exec('git tag -l', $tags);

    // Sort tags numerically
    usort($tags, function ($a, $b) {
        return version_compare($a, $b);
    });

    $latestTag = '0.0.0';
    if (count($tags) > 0) {
        echo "Existing tags:\n";
        foreach ($tags as $tag) {
            echo " - $tag\n";
        }
        $latestTag = end($tags);
        echo "\n";
    }

    echo "{$packageName} {$latestTag}\n";
    echo "Usage: lando php bin/release.php <version>\n";
    exit(1);
}

$version = $argv[1];

// Validate version format (X.Y.Z)
if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
    echo "Error: Version must be in format X.Y.Z (e.g., 1.15.0)\n";
    exit(1);
}

// --- Changelog Generation ---
echo "📝 Generating changelog...\n";

// 1. Find the previous tag
// 'git describe' finds the most recent tag reachable from HEAD
$previousTag = trim(shell_exec("git describe --tags --abbrev=0 2>/dev/null") ?? '');

// 2. Determine the git log range
if ($previousTag) {
    // From previous tag to current HEAD
    $range = "$previousTag..HEAD";
    echo "   Collecting commits from $previousTag to HEAD...\n";
} else {
    // No tags exist yet, log everything
    $range = "HEAD";
    echo "   First release! Collecting all commits...\n";
}

// 3. Get the commits
// Format: "- Commit message (Author Name)"
$commits = [];
exec("git log $range --pretty=format:\"- %s (%an)\" --no-merges", $commits);

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

// 1. Tag
exec("git tag -l {$version}", $existingTags);
if (in_array($version, $existingTags)) {
    echo "ℹ️  Tag $version already exists.\n";
} else {
    // Create annotated tag with commit messages
    $tagMessage = "Release $version\n\n" . implode("\n", $commits);
    $tempMsgFile = tempnam(sys_get_temp_dir(), 'sf_release');
    file_put_contents($tempMsgFile, $tagMessage);

    runCommand("git tag -a {$version} -F {$tempMsgFile}");

    unlink($tempMsgFile);
    echo "✅ Created annotated tag {$version}\n";
}

// 2. Push
echo "\nPushing to remote...\n";
runCommand("git push origin HEAD");
runCommand("git push origin {$version}");

echo "\n🎉 Release $version completed successfully!\n";
