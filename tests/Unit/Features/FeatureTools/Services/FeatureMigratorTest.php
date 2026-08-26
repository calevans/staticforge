<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\FeatureTools\Services;

use EICC\StaticForge\Features\FeatureTools\Services\FeatureMigrator;
use PHPUnit\Framework\TestCase;

class FeatureMigratorTest extends TestCase
{
    private FeatureMigrator $migrator;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->migrator = new FeatureMigrator();
        $this->tempDir = sys_get_temp_dir() . '/feature_migrator_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function writeFixture(string $content): string
    {
        $path = $this->tempDir . '/Feature.php';
        file_put_contents($path, $content);
        return $path;
    }

    public function testDetectsAlreadyMigratedFile(): void
    {
        $path = $this->writeFixture(<<<'PHP'
            <?php

            declare(strict_types=1);

            namespace EICC\StaticForge\Features\Foo;

            use EICC\StaticForge\Core\BaseFeature;
            use EICC\StaticForge\Core\Events\Event;
            use EICC\StaticForge\Core\Events\EventListener;

            class Feature extends BaseFeature
            {
                #[EventListener('CREATE', priority: 10)]
                public function handleCreate(Event $event): void
                {
                }
            }
            PHP);

        $result = $this->migrator->migrateFile($path);

        $this->assertTrue($result->alreadyMigrated);
        $this->assertFalse($result->skipped);
        $this->assertFalse($result->changed());
    }

    public function testSkipsFileWithNoEventListenersAndNoAttributes(): void
    {
        $path = $this->writeFixture(<<<'PHP'
            <?php
            namespace EICC\StaticForge\Features\Foo;
            class NotAFeature
            {
            }
            PHP);

        $result = $this->migrator->migrateFile($path);

        $this->assertFalse($result->alreadyMigrated);
        $this->assertTrue($result->skipped);
        $this->assertNotNull($result->skipReason);
    }

    public function testSkipsFileWithUnknownEventName(): void
    {
        $path = $this->writeFixture(<<<'PHP'
            <?php
            namespace EICC\StaticForge\Features\Foo;
            use EICC\StaticForge\Core\BaseFeature;
            use EICC\Utils\Container;
            class Feature extends BaseFeature
            {
                protected array $eventListeners = [
                    'SOME_THIRD_PARTY_EVENT' => ['method' => 'handleIt', 'priority' => 10]
                ];

                public function handleIt(Container $container, array $parameters): array
                {
                    return $parameters;
                }
            }
            PHP);

        $result = $this->migrator->migrateFile($path);

        $this->assertTrue($result->skipped);
        $this->assertNotNull($result->skipReason);
        $this->assertStringContainsString('Unknown event', $result->skipReason);
    }

    public function testConvertsASimpleContainerOnlyHandler(): void
    {
        // Real shape: CacheBuster's Feature.php before its 3.0 migration.
        $path = $this->writeFixture(<<<'PHP'
            <?php

            namespace EICC\StaticForge\Features\CacheBuster;

            use EICC\StaticForge\Core\BaseFeature;
            use EICC\StaticForge\Core\FeatureInterface;
            use EICC\StaticForge\Core\EventManager;
            use EICC\Utils\Container;
            use EICC\Utils\Log;

            class Feature extends BaseFeature implements FeatureInterface
            {
                protected string $name = 'CacheBuster';
                protected Log $logger;

                /**
                 * @var array<string, array{method: string, priority: int}>
                 */
                protected array $eventListeners = [
                    'CREATE' => ['method' => 'handleCreate', 'priority' => 10]
                ];

                public function register(EventManager $eventManager): void
                {
                    parent::register($eventManager);
                }

                /**
                 * @param Container $container
                 * @param array<string, mixed> $parameters
                 * @return array<string, mixed>
                 */
                public function handleCreate(Container $container, array $parameters): array
                {
                    $buildId = uniqid();
                    $container->setVariable('build_id', $buildId);
                    return $parameters;
                }
            }
            PHP);

        $result = $this->migrator->migrateFile($path);

        $this->assertFalse($result->skipped);
        $this->assertTrue($result->changed());
        $this->assertSame(1, $result->listenersConverted);

        $this->assertStringContainsString("#[EventListener('CREATE', priority: 10)]", $result->migratedContent);
        $this->assertStringContainsString('public function handleCreate(Event $event): void', $result->migratedContent);
        $this->assertStringNotContainsString('protected array $eventListeners', $result->migratedContent);
        $this->assertStringNotContainsString('return $parameters;', $result->migratedContent);
        $this->assertStringContainsString(
            'use EICC\StaticForge\Core\Events\EventListener;',
            $result->migratedContent
        );
        $this->assertStringContainsString('use EICC\StaticForge\Core\Events\Event;', $result->migratedContent);

        // Container is still used in the body — must be flagged, not silently dropped.
        $this->assertStringContainsString('TODO(feature:migrate)', $result->migratedContent);
        $this->assertStringContainsString("\$container->setVariable('build_id'", $result->migratedContent);
        $this->assertNotEmpty($result->warnings);

        // The output must itself be valid PHP.
        $this->assertGeneratedCodeParses($result->migratedContent);
    }

    public function testConvertsRenderEventFieldsAndEarlyReturns(): void
    {
        // Real shape: EstimatedReadingTime's Feature.php before its 3.0 migration —
        // multiple early "return $context;" guard clauses and a file_path read.
        $path = $this->writeFixture(<<<'PHP'
            <?php

            declare(strict_types=1);

            namespace EICC\StaticForge\Features\EstimatedReadingTime;

            use EICC\StaticForge\Core\BaseFeature;
            use EICC\StaticForge\Core\FeatureInterface;
            use EICC\Utils\Container;

            class Feature extends BaseFeature implements FeatureInterface
            {
                protected string $name = 'EstimatedReadingTime';

                protected array $eventListeners = [
                    'PRE_RENDER' => ['method' => 'handlePreRender', 'priority' => 50]
                ];

                public function handlePreRender(Container $container, array $context): array
                {
                    $filePath = $context['file_path'] ?? null;
                    if (!$filePath) {
                        return $context;
                    }

                    if (!isset($context['file_metadata'])) {
                        $context['file_metadata'] = [];
                    }
                    $context['file_metadata']['reading_time_minutes'] = 2;

                    return $context;
                }
            }
            PHP);

        $result = $this->migrator->migrateFile($path);

        $this->assertFalse($result->skipped);
        $this->assertSame(1, $result->listenersConverted);

        $this->assertStringContainsString(
            'public function handlePreRender(RenderEvent $event): void',
            $result->migratedContent
        );
        $this->assertStringContainsString('$event->filePath', $result->migratedContent);
        $this->assertStringContainsString("\$event->metadata['reading_time_minutes']", $result->migratedContent);

        // Every "return $context;" — including the early guard clause — becomes bare "return;".
        $this->assertStringNotContainsString('return $context;', $result->migratedContent);
        $this->assertSame(2, substr_count($result->migratedContent, 'return;'));

        $this->assertGeneratedCodeParses($result->migratedContent);
    }

    public function testSimplifiesRegisterSignatureWhenContainerParamPresent(): void
    {
        $path = $this->writeFixture(<<<'PHP'
            <?php
            namespace EICC\StaticForge\Features\Foo;
            use EICC\StaticForge\Core\BaseFeature;
            use EICC\StaticForge\Core\EventManager;
            use EICC\Utils\Container;
            class Feature extends BaseFeature
            {
                protected array $eventListeners = [
                    'CREATE' => ['method' => 'handleCreate', 'priority' => 10]
                ];

                public function register(EventManager $eventManager, Container $container): void
                {
                    parent::register($eventManager, $container);
                }

                public function handleCreate(Container $container, array $parameters): array
                {
                    return $parameters;
                }
            }
            PHP);

        $result = $this->migrator->migrateFile($path);

        $this->assertFalse($result->skipped);
        $this->assertStringContainsString(
            'public function register(EventManager $eventManager): void',
            $result->migratedContent
        );
        $this->assertGeneratedCodeParses($result->migratedContent);
    }

    private function assertGeneratedCodeParses(string $code): void
    {
        $tmpFile = $this->tempDir . '/parse_check_' . uniqid() . '.php';
        file_put_contents($tmpFile, $code);

        $output = [];
        $exitCode = 0;
        exec('php -l ' . escapeshellarg($tmpFile) . ' 2>&1', $output, $exitCode);

        $this->assertSame(0, $exitCode, "Generated code does not parse:\n" . implode("\n", $output) . "\n\n{$code}");
    }
}
