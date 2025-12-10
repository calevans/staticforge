<?php

/**
 * Feature Extraction Script with Auto-Dependency Discovery & Test Harness Generation
 *
 * Usage: php scripts/extract_feature.php <FeatureName> <VendorName> <PackageName> <TargetNamespace>
 * Example: php scripts/extract_feature.php S3MediaOffload calevans staticforge-s3 "Calevans\\StaticForgeS3"
 */

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

if ($argc < 5) {
    echo "Usage: php scripts/extract_feature.php <FeatureName> <VendorName> <PackageName> <TargetNamespace>\n";
    echo "Example: php scripts/extract_feature.php S3MediaOffload calevans staticforge-s3 \"Calevans\\\\StaticForgeS3\"\n";
    exit(1);
}

$featureName = $argv[1];
$vendorName = $argv[2];
$packageName = $argv[3];
$targetNamespace = trim($argv[4], '\\');
$customTargetDir = $argv[5] ?? null;

$sourceDir = __DIR__ . '/../src/Features/' . $featureName;
// Target is one level up from the project root, or custom path
if ($customTargetDir) {
    $targetDir = $customTargetDir;
} else {
    $targetDir = dirname(__DIR__, 2) . '/' . $packageName;
}

// Validation
if (!is_dir($sourceDir)) {
    die("Error: Feature directory not found: $sourceDir\n");
}

if (is_dir($targetDir)) {
    die("Error: Target directory already exists: $targetDir\n");
}

echo "Extracting Feature: $featureName\n";
echo "Source: $sourceDir\n";
echo "Target: $targetDir\n";
echo "Namespace: EICC\\StaticForge\\Features\\$featureName -> $targetNamespace\n";

// --- Dependency Discovery Logic ---

echo "Analyzing dependencies...\n";

// 1. Build Package Map from installed.json
$packageMap = [];
$installedJsonPath = __DIR__ . '/../vendor/composer/installed.json';
if (file_exists($installedJsonPath)) {
    $installedData = json_decode(file_get_contents($installedJsonPath), true);
    $packages = $installedData['packages'] ?? []; // Composer 2 format

    foreach ($packages as $pkg) {
        if (isset($pkg['autoload']['psr-4'])) {
            foreach ($pkg['autoload']['psr-4'] as $ns => $path) {
                // Normalize namespace
                $ns = trim($ns, '\\');
                $packageMap[$ns] = [
                    'name' => $pkg['name'],
                    'version' => $pkg['version']
                ];
            }
        }
    }
} else {
    echo "Warning: vendor/composer/installed.json not found. Skipping dependency discovery.\n";
}

// Helper to scan directory for used namespaces
function scanDirectoryForNamespaces(string $dir): array {
    $usedNamespaces = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isFile() && $item->getExtension() === 'php') {
            $content = file_get_contents($item->getPathname());
            $tokens = token_get_all($content);
            $count = count($tokens);

            for ($i = 0; $i < $count; $i++) {
                $token = $tokens[$i];

                // Check for 'use Namespace\Class;'
                if (is_array($token) && $token[0] === T_USE) {
                    $ns = '';
                    $j = $i + 1;
                    while ($j < $count) {
                        $t = $tokens[$j];
                        if ($t === ';' || $t === '{' || (is_array($t) && $t[0] === T_AS)) break;

                        if (is_array($t) && ($t[0] === T_STRING || $t[0] === T_NAME_QUALIFIED || $t[0] === T_NAME_FULLY_QUALIFIED || $t[0] === T_NS_SEPARATOR)) {
                            $ns .= $t[1];
                        }
                        $j++;
                    }
                    if ($ns) $usedNamespaces[] = trim($ns, '\\');
                }

                // Check for inline fully qualified names (PHP 8 T_NAME_FULLY_QUALIFIED)
                if (is_array($token) && defined('T_NAME_FULLY_QUALIFIED') && $token[0] === T_NAME_FULLY_QUALIFIED) {
                    $usedNamespaces[] = trim($token[1], '\\');
                }
            }
        }
    }
    return array_unique($usedNamespaces);
}

// 3. Match Usages to Packages
function resolveDependencies(array $namespaces, array $packageMap): array {
    $deps = [];
    foreach ($namespaces as $usedNs) {
        foreach ($packageMap as $pkgNs => $pkgInfo) {
            if (str_starts_with($usedNs, $pkgNs)) {
                if ($pkgInfo['name'] !== 'staticforge/staticforge') {
                     $deps[$pkgInfo['name']] = $pkgInfo['version'];
                }
                break;
            }
        }
    }
    return $deps;
}

$detectedDependencies = resolveDependencies(scanDirectoryForNamespaces($sourceDir), $packageMap);

echo "Detected Dependencies:\n";
foreach ($detectedDependencies as $name => $ver) {
    echo "  - $name: $ver\n";
}

// --- Extraction Logic ---

// Create directories
if (!mkdir($targetDir, 0755, true)) {
    die("Error: Failed to create target directory.\n");
}
mkdir($targetDir . '/src', 0755, true);

// Copy Project Files (LICENSE, CODE_OF_CONDUCT.md)
foreach (['LICENSE', 'CODE_OF_CONDUCT.md'] as $file) {
    $sourcePath = __DIR__ . '/../' . $file;
    if (file_exists($sourcePath)) {
        copy($sourcePath, $targetDir . '/' . $file);
        echo "Copied $file\n";
    }
}

// Copy files and replace namespace
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $item) {
    $subPath = $iterator->getSubPathName();
    $targetPath = $targetDir . '/src/' . $subPath;

    if ($item->isDir()) {
        mkdir($targetPath);
    } else {
        $content = file_get_contents($item->getPathname());

        // Namespace replacement
        $oldNamespace = "EICC\\StaticForge\\Features\\$featureName";
        $content = str_replace($oldNamespace, $targetNamespace, $content);

        file_put_contents($targetPath, $content);
    }
}

// --- Test Extraction Logic ---
echo "Extracting Tests...\n";
$sourceUnitTestDir = __DIR__ . '/../tests/Unit/Features/' . $featureName;
$sourceUnitTestFile = __DIR__ . '/../tests/Unit/Features/' . $featureName . 'FeatureTest.php';
$sourceIntegrationTestDir = __DIR__ . '/../tests/Integration/Features/' . $featureName;
$sourceIntegrationTestFile = __DIR__ . '/../tests/Integration/Features/' . $featureName . 'IntegrationTest.php';

$targetTestDir = $targetDir . '/tests';
$targetUnitTestDir = $targetTestDir . '/Unit';
$targetIntegrationTestDir = $targetTestDir . '/Integration';

$testNamespaces = [];

// Helper to copy and rewrite tests
function copyAndRewriteTests($source, $dest, $oldNs, $newNs, $featureName, &$testNamespaces) {
    if (is_dir($source)) {
        if (!is_dir($dest)) mkdir($dest, 0755, true);

        // Scan for dependencies in tests
        $testNamespaces = array_merge($testNamespaces, scanDirectoryForNamespaces($source));

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $subPath = $iterator->getSubPathName();
            $targetPath = $dest . '/' . $subPath;

            if ($item->isDir()) {
                if (!is_dir($targetPath)) mkdir($targetPath);
            } else {
                $content = file_get_contents($item->getPathname());
                $content = rewriteTestContent($content, $oldNs, $newNs, $featureName);
                file_put_contents($targetPath, $content);
            }
        }
    } elseif (file_exists($source)) {
        if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0755, true);

        // Scan file for dependencies
        // (Simple hack: make a temp dir to reuse scan function or just parse file)
        // For now, let's just parse the file content manually if needed, but scanDirectory works on dirs.
        // We'll skip scanning single files for now to keep it simple, or assume dir scan caught most.

        $content = file_get_contents($source);
        $content = rewriteTestContent($content, $oldNs, $newNs, $featureName);
        file_put_contents($dest, $content);
    }
}

function rewriteTestContent($content, $oldNs, $newNs, $featureName) {
    // Rewrite Namespace
    // Handle both standard and legacy/incorrect namespaces
    $content = str_replace("EICC\\StaticForge\\Tests\\Unit\\Features\\$featureName", "$newNs\\Tests\\Unit", $content);
    $content = str_replace("Tests\\Unit\\Features\\$featureName", "$newNs\\Tests\\Unit", $content);

    $content = str_replace("EICC\\StaticForge\\Tests\\Integration\\Features\\$featureName", "$newNs\\Tests\\Integration", $content);
    $content = str_replace("Tests\\Integration\\Features\\$featureName", "$newNs\\Tests\\Integration", $content);

    // Rewrite Feature Usage
    $content = str_replace("EICC\\StaticForge\\Features\\$featureName", $newNs, $content);

    // Rewrite UnitTestCase inheritance
    $content = str_replace("use EICC\\StaticForge\\Tests\\Unit\\UnitTestCase;", "use $newNs\\Tests\\TestCase;", $content);
    $content = str_replace("extends UnitTestCase", "extends TestCase", $content);

    // Rewrite Integration inheritance (if any)
    // Assuming integration tests might also extend UnitTestCase or similar

    return $content;
}

// Copy Unit Tests
copyAndRewriteTests($sourceUnitTestDir, $targetUnitTestDir, $targetNamespace, $targetNamespace, $featureName, $testNamespaces);
copyAndRewriteTests($sourceUnitTestFile, $targetUnitTestDir . '/FeatureTest.php', $targetNamespace, $targetNamespace, $featureName, $testNamespaces);

// Copy Integration Tests
copyAndRewriteTests($sourceIntegrationTestDir, $targetIntegrationTestDir, $targetNamespace, $targetNamespace, $featureName, $testNamespaces);
copyAndRewriteTests($sourceIntegrationTestFile, $targetIntegrationTestDir . '/IntegrationTest.php', $targetNamespace, $targetNamespace, $featureName, $testNamespaces);

// Resolve Dev Dependencies
$detectedTestDependencies = resolveDependencies($testNamespaces, $packageMap);

// Generate Base TestCase
$testCaseContent = <<<PHP
<?php

namespace $targetNamespace\\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use EICC\Utils\Container;
use EICC\Utils\Log;

class TestCase extends BaseTestCase
{
    protected Container \$container;
    protected Log \$logger;

    protected function setUp(): void
    {
        parent::setUp();
        \$this->container = new Container();
        \$this->logger = new Log();
        \$this->container->setVariable('logger', \$this->logger);
    }
}
PHP;
file_put_contents($targetTestDir . '/TestCase.php', $testCaseContent);


// Generate composer.json
$composerJson = [
    "name" => "$vendorName/$packageName",
    "description" => "StaticForge Feature: $featureName",
    "type" => "library",
    "license" => "MIT",
    "autoload" => [
        "psr-4" => [
            "$targetNamespace\\" => "src/"
        ]
    ],
    "autoload-dev" => [
        "psr-4" => [
            "$targetNamespace\\Tests\\" => "tests/"
        ]
    ],
    "require" => [
        "php" => "^8.4"
    ],
    "require-dev" => [
        "phpunit/phpunit" => "^10.0"
    ],
    "extra" => [
        "staticforge" => [
            "feature" => "$targetNamespace\\Feature"
        ]
    ]
];

// Add detected dependencies
foreach ($detectedDependencies as $name => $version) {
    if (preg_match('/^v?(\d+\.\d+)/', $version, $matches)) {
        $version = '^' . $matches[1];
    }
    $composerJson['require'][$name] = $version;
}

// Add detected dev dependencies
foreach ($detectedTestDependencies as $name => $version) {
    if (!isset($composerJson['require'][$name])) { // Don't add if already in require
        if (preg_match('/^v?(\d+\.\d+)/', $version, $matches)) {
            $version = '^' . $matches[1];
        }
        $composerJson['require-dev'][$name] = $version;
    }
}

file_put_contents(
    $targetDir . '/composer.json',
    json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

// Generate README.md
$readmeContent = "# $featureName\n\n";
$readmeContent .= "A StaticForge feature package.\n\n";
$readmeContent .= "## Installation\n\n";
$readmeContent .= "```bash\n";
$readmeContent .= "composer require $vendorName/$packageName\n";
$readmeContent .= "```\n";

file_put_contents($targetDir . '/README.md', $readmeContent);

echo "\nSuccess! Feature extracted to $targetDir\n";
echo "To use this package locally for development:\n";
echo "1. Add the repository to your main composer.json:\n";
echo "   \"repositories\": [\n";
echo "       { \"type\": \"path\", \"url\": \"../$packageName\" }\n";
echo "   ]\n";
echo "2. Run: composer require $vendorName/$packageName @dev\n";

if (!empty($detectedDependencies)) {
    echo "\nIMPORTANT: The following dependencies were detected and added to the new package:\n";
    foreach ($detectedDependencies as $name => $ver) {
        echo "  $name\n";
    }
    echo "You should now REMOVE them from the main project to avoid conflicts/bloat:\n";
    echo "  composer remove " . implode(' ', array_keys($detectedDependencies)) . "\n";
}

echo "\nNOTE: Check if your feature uses any global templates in 'templates/' and move them to your package if necessary.\n";

// --- Verification Logic ---
echo "\nVerifying extraction...\n";

function verifyDirectoryContents($source, $targetBase) {
    if (!is_dir($source)) return true;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $relativePath = $iterator->getSubPathName();
        $targetPath = $targetBase . '/' . $relativePath;

        if ($item->isDir()) {
            if (!is_dir($targetPath)) {
                echo "Verification Failed: Target directory missing: $targetPath\n";
                return false;
            }
        } else {
            if (!file_exists($targetPath)) {
                echo "Verification Failed: Target file missing: $targetPath\n";
                return false;
            }
            if (filesize($targetPath) === 0) {
                 echo "Verification Failed: Target file is empty: $targetPath\n";
                 return false;
            }
        }
    }
    return true;
}

$verificationPassed = true;
$verificationPassed &= verifyDirectoryContents($sourceDir, $targetDir . '/src');
$verificationPassed &= verifyDirectoryContents($sourceUnitTestDir, $targetUnitTestDir);
$verificationPassed &= verifyDirectoryContents($sourceIntegrationTestDir, $targetIntegrationTestDir);

// Check single files if they exist
if (file_exists($sourceUnitTestFile)) {
    $targetFile = $targetUnitTestDir . '/FeatureTest.php';
    if (!file_exists($targetFile) || filesize($targetFile) === 0) {
        echo "Verification Failed: Unit Test file missing or empty: $targetFile\n";
        $verificationPassed = false;
    }
}

if (file_exists($sourceIntegrationTestFile)) {
    $targetFile = $targetIntegrationTestDir . '/IntegrationTest.php';
    if (!file_exists($targetFile) || filesize($targetFile) === 0) {
        echo "Verification Failed: Integration Test file missing or empty: $targetFile\n";
        $verificationPassed = false;
    }
}

// Check project files
foreach (['LICENSE', 'CODE_OF_CONDUCT.md'] as $file) {
    if (file_exists(__DIR__ . '/../' . $file)) {
        if (!file_exists($targetDir . '/' . $file)) {
            echo "Verification Failed: Project file missing: $file\n";
            $verificationPassed = false;
        }
    }
}

if (!$verificationPassed) {
    die("Extraction verification failed. Source files were NOT deleted.\n");
}

echo "Verification successful.\n";

// --- Cleanup Logic ---
echo "\nCleaning up source files...\n";

foreach ([
    $sourceDir,
    $sourceUnitTestDir,
    $sourceUnitTestFile,
    $sourceIntegrationTestDir,
    $sourceIntegrationTestFile
] as $path) {
    if (is_dir($path)) {
        // Recursive delete
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($path);
        echo "Deleted directory: $path\n";
    } elseif (file_exists($path)) {
        unlink($path);
        echo "Deleted file: $path\n";
    }
}


