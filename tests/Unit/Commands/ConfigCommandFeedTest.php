<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Commands;

use EICC\StaticForge\Commands\Audit\ConfigCommand;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * audit:config validation of the `feed:` block.
 */
class ConfigCommandFeedTest extends UnitTestCase
{
    private string $testDir;
    private string $originalCwd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCwd = (string) getcwd();
        $this->testDir = sys_get_temp_dir() . '/staticforge_config_feed_test_' . uniqid();
        mkdir($this->testDir);
        mkdir($this->testDir . '/content');
        mkdir($this->testDir . '/templates');
        touch($this->testDir . '/.env');
        chdir($this->testDir);
        $this->setContainerVariable('TEMPLATE', 'sample');
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        $this->removeDirectory($this->testDir);
        parent::tearDown();
    }

    /**
     * @param mixed $feed Value for `feed:`; null omits the key
     */
    private function audit(mixed $feed): CommandTester
    {
        $config = ['site' => ['name' => 'Test Site']];
        if ($feed !== null) {
            $config['feed'] = $feed;
        }
        $this->setContainerVariable('site_config', $config);

        $application = new Application();
        $application->addCommand(new ConfigCommand($this->container));
        $tester = new CommandTester($application->find('audit:config'));
        $tester->execute([]);

        return $tester;
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function validFeedBlocks(): array
    {
        return [
            'key absent' => [null],
            'minimal' => [['enabled' => true]],
            'disabled' => [['enabled' => false]],
            'full' => [[
                'enabled' => true,
                'limit' => 20,
                'formats' => ['rss', 'atom', 'json'],
                'exclude_categories' => ['podcast', 'News & Views'],
                'category_formats' => ['rss', 'atom'],
            ]],
            'empty formats list' => [['formats' => []]],
            'mixed case formats' => [['formats' => ['RSS', ' Atom ']]],
            'empty exclude list' => [['exclude_categories' => []]],
            'enabled yes' => [['enabled' => 'yes']],
            'enabled int' => [['enabled' => 1]],
            'formats as mapping' => [['formats' => ['a' => 'rss']]],
            'exclude_categories as mapping' => [['exclude_categories' => ['a' => 'b']]],
            'exclude_categories numeric' => [['exclude_categories' => ['ok', 12]]],
        ];
    }

    #[DataProvider('validFeedBlocks')]
    public function testValidFeedBlockPassesTheAudit(mixed $feed): void
    {
        $tester = $this->audit($feed);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString('Audit passed', $tester->getDisplay());
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidFeedBlocks(): array
    {
        return [
            'limit non numeric string' => [['limit' => 'abc'], 'feed.limit must be a positive integer'],
            'limit zero' => [['limit' => 0], 'feed.limit must be a positive integer'],
            'limit negative' => [['limit' => -1], 'feed.limit must be a positive integer'],
            'limit float' => [['limit' => 2.5], 'feed.limit must be a positive integer'],
            'limit numeric string' => [['limit' => '5'], 'feed.limit must be a positive integer'],
            'formats as string' => [['formats' => 'rss'], 'feed.formats must be a list'],
            'unknown format' => [['formats' => ['rss', 'rdf']], 'feed.formats contains an unknown format and it was ignored'],
            'non string format' => [['formats' => [1]], 'feed.formats contains an unknown format'],
            'category_formats as string' => [['category_formats' => 'atom'], 'feed.category_formats must be a list'],
            'category_formats unknown' => [['category_formats' => ['opml']], 'feed.category_formats contains an unknown format'],
            'exclude_categories as string' => [['exclude_categories' => 'podcast'], 'feed.exclude_categories must be a list'],
            'enabled garbage' => [['enabled' => 'maybe'], 'feed.enabled must be true or false'],
            'enabled array' => [['enabled' => ['x']], 'feed.enabled must be true or false'],
            'feed is a scalar' => ['on', "'feed' in siteconfig.yaml must be a mapping"],
            'feed is a bool' => [true, "'feed' in siteconfig.yaml must be a mapping"],
        ];
    }

    #[DataProvider('invalidFeedBlocks')]
    public function testInvalidFeedBlockFailsTheAuditWithAnExplanation(mixed $feed, string $message): void
    {
        $tester = $this->audit($feed);

        $this->assertSame(1, $tester->getStatusCode());
        $display = preg_replace('/\s+/', ' ', $tester->getDisplay());
        $this->assertStringContainsString('SiteFeed', (string) $display);
        $this->assertStringContainsString($message, (string) $display);
    }

    public function testEveryProblemInABlockIsReported(): void
    {
        $tester = $this->audit(['enabled' => 'maybe', 'limit' => 0, 'formats' => 'rss', 'exclude_categories' => 'x']);

        $display = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        foreach (['feed.enabled', 'feed.limit', 'feed.formats', 'feed.exclude_categories'] as $key) {
            $this->assertStringContainsString($key, $display);
        }
    }
}
