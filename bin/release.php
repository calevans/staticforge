#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Release Helper Script
 *
 * Generates a changelog from git history, tags the release, and pushes the tag.
 * Version is tracked by git tags only — composer.json has no "version"
 * field. Composer resolves the installed version from the tag itself;
 * a hardcoded "version" field that drifts out of sync with the tag is
 * silently dropped by Packagist (it can't tell which one is right).
 *
 * Usage: lando php bin/release.php <version> [--push-branch]
 *
 * This script tags an already-published commit. It does not push the branch
 * by default: doing so unconditionally walked straight through branch
 * protection on every release. Pass --push-branch to push it anyway.
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

/**
 * Highest existing tag by version ordering, or '0.0.0' when there are none.
 *
 * @return array{0: string, 1: array<int, string>}
 */
function latestTag(): array
{
    $tags = [];
    exec('git tag -l', $tags);
    $tags = array_values(array_filter($tags, static fn(string $t): bool => $t !== ''));

    usort($tags, static fn(string $a, string $b): int => version_compare($a, $b));

    return [count($tags) > 0 ? (string) end($tags) : '0.0.0', $tags];
}

// Bring remote tags and branch state local before deciding anything: a tag
// cut elsewhere still shapes what the next version may be.
echo "Fetching remote refs...\n";
exec('git fetch --tags --quiet origin 2>&1', $fetchOut, $fetchStatus);
if ($fetchStatus !== 0) {
    echo "Error: could not fetch from origin.\n" . implode("\n", $fetchOut) . "\n";
    exit(1);
}

$pushBranch = in_array('--push-branch', array_slice($argv, 1), true);
$positional = array_values(array_filter(
    array_slice($argv, 1),
    static fn(string $a): bool => !str_starts_with($a, '--')
));

if (count($positional) !== 1) {
    [$latest, $tags] = latestTag();

    if (count($tags) > 0) {
        echo "Existing tags:\n";
        foreach ($tags as $tag) {
            echo " - $tag\n";
        }
        echo "\n";
    }

    echo "{$packageName} {$latest}\n";
    echo "Usage: lando php bin/release.php <version> [--push-branch]\n";
    exit(1);
}

$version = $positional[0];

// Validate version format (X.Y.Z)
if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
    echo "Error: Version must be in format X.Y.Z (e.g., 1.15.0)\n";
    exit(1);
}

// A version at or below the highest existing tag is never resolved as the
// latest release by Composer, so the release would silently go nowhere.
[$latest] = latestTag();
if (version_compare($version, $latest, '<=')) {
    echo "Error: {$version} is not higher than the latest tag {$latest}.\n";
    echo "       Composer orders releases by version, so {$version} would never\n";
    echo "       be resolved as the newest release. Pick a version above {$latest}.\n";
    exit(1);
}

// A dirty tree means the tag would not describe what is actually committed.
// Untracked files are ignored: they cannot change what the tag points at.
$statusLines = [];
exec('git status --porcelain --untracked-files=no', $statusLines);
if (array_filter($statusLines, static fn(string $l): bool => trim($l) !== '')) {
    echo "Error: working tree is not clean. Commit or stash before releasing.\n";
    foreach ($statusLines as $line) {
        echo "  {$line}\n";
    }
    exit(1);
}

// The tag must point at a commit the remote already has. Pushing the branch
// from here is what bypassed branch protection on every previous release.
$branch = trim((string) shell_exec('git rev-parse --abbrev-ref HEAD'));
$remoteRef = trim((string) shell_exec('git rev-parse --abbrev-ref --symbolic-full-name @{u} 2>/dev/null'));
if ($remoteRef === '') {
    $remoteRef = "origin/{$branch}";
}

exec('git rev-parse --verify --quiet ' . escapeshellarg($remoteRef), $refOut, $refStatus);
$remoteKnown = $refStatus === 0;

$published = false;
if ($remoteKnown) {
    exec('git merge-base --is-ancestor HEAD ' . escapeshellarg($remoteRef), $mbOut, $mbStatus);
    $published = $mbStatus === 0;
}

if (!$published) {
    if (!$pushBranch) {
        echo "Error: HEAD is not published on {$remoteRef}.\n";
        echo "       Release tags a commit the remote already has. Get it there first\n";
        echo "       (open a pull request, or push the branch), then re-run.\n";
        echo "       To push directly from here anyway: --push-branch\n";
        exit(1);
    }

    echo "⚠️  HEAD is not on {$remoteRef}; --push-branch given, pushing directly.\n";
    echo "    This bypasses any pull-request rule protecting {$branch}.\n";
}

// --- Changelog Generation ---
echo "📝 Generating changelog...\n";

// 'git describe' finds the most recent tag reachable from HEAD
$previousTag = trim(shell_exec("git describe --tags --abbrev=0 2>/dev/null") ?? '');

if ($previousTag) {
    $range = "$previousTag..HEAD";
    echo "   Collecting commits from $previousTag to HEAD...\n";
} else {
    $range = "HEAD";
    echo "   First release! Collecting all commits...\n";
}

// Format: "- Commit message (Author Name)"
$commits = [];
exec("git log $range --pretty=format:\"- %s (%an)\" --no-merges", $commits);

// Helper to run commands
function runCommand(string $cmd): void
{
    echo "> $cmd\n";
    passthru($cmd, $returnVar);
    if ($returnVar !== 0) {
        echo "❌ Command failed: $cmd\n";
        exit(1);
    }
}

echo "\nStarting git operations...\n";

// 1. Tag
$existingTags = [];
exec("git tag -l " . escapeshellarg($version), $existingTags);
if (in_array($version, $existingTags, true)) {
    echo "ℹ️  Tag $version already exists.\n";
} else {
    // Create annotated tag with commit messages
    $tagMessage = "Release $version\n\n" . implode("\n", $commits) . "\n";
    $tempMsgFile = tempnam(sys_get_temp_dir(), 'sf_release');
    file_put_contents($tempMsgFile, $tagMessage);

    runCommand("git tag -a {$version} -F {$tempMsgFile}");

    unlink($tempMsgFile);
    echo "✅ Created annotated tag {$version}\n";
}

// 2. Push
echo "\nPushing to remote...\n";
if ($pushBranch && !$published) {
    runCommand("git push origin HEAD");
}
runCommand("git push origin {$version}");

echo "\n🎉 Release $version completed successfully!\n";
