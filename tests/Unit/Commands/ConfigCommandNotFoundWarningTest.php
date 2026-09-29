<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Commands;

use EICC\StaticForge\Commands\Audit\ConfigCommand;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ConfigCommandNotFoundWarningTest extends UnitTestCase
{
    private const WARNING = '404 pages need an absolute base URL';

    private string $testDir;
    private string $originalCwd;
    private mixed $originalEnvBaseUrl;
    private string|false $originalGetenvBaseUrl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCwd = (string) getcwd();
        $this->originalEnvBaseUrl = $_ENV['SITE_BASE_URL'] ?? null;
        $this->originalGetenvBaseUrl = getenv('SITE_BASE_URL');

        $this->testDir = sys_get_temp_dir() . '/staticforge_config404_test_' . uniqid();
        mkdir($this->testDir . '/content', 0755, true);
        touch($this->testDir . '/.env');
        chdir($this->testDir);

        $this->setContainerVariable('TEMPLATE', 'sample');
        $this->setContainerVariable('site_config', ['site' => ['name' => 'Test Site']]);
        $this->setContainerVariable('SOURCE_DIR', $this->testDir . '/content');
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        if ($this->originalEnvBaseUrl === null) {
            unset($_ENV['SITE_BASE_URL']);
        } else {
            $_ENV['SITE_BASE_URL'] = $this->originalEnvBaseUrl;
        }
        putenv(
            $this->originalGetenvBaseUrl === false
                ? 'SITE_BASE_URL'
                : 'SITE_BASE_URL=' . $this->originalGetenvBaseUrl
        );
        $this->removeDirectory($this->testDir);
        parent::tearDown();
    }

    /**
     * @return array{0: int, 1: string} exit code and whitespace-normalised display
     */
    private function audit(string $baseUrl): array
    {
        $_ENV['SITE_BASE_URL'] = $baseUrl;
        putenv('SITE_BASE_URL=' . $baseUrl);

        $application = new Application();
        $application->addCommand(new ConfigCommand($this->container));
        $tester = new CommandTester($application->find('audit:config'));
        $exitCode = $tester->execute([]);

        return [$exitCode, (string) preg_replace('/[\s│]+/u', ' ', $tester->getDisplay())];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function badBaseUrlProvider(): array
    {
        return [
            'md empty' => ['404.md', ''],
            'md host-relative sub path' => ['404.md', '/sub/'],
            'md root only' => ['404.md', '/'],
            'md no scheme' => ['404.md', 'example.com/'],
            'html empty' => ['404.html', ''],
            'html host-relative sub path' => ['404.html', '/sub/'],
        ];
    }

    #[DataProvider('badBaseUrlProvider')]
    public function testWarnsWhen404FileExistsAndBaseUrlIsNotAbsolute(string $file, string $baseUrl): void
    {
        touch($this->testDir . '/content/' . $file);

        [, $display] = $this->audit($baseUrl);

        $this->assertStringContainsString(self::WARNING, $display);
    }

    public function testDoesNotWarnWhenBaseUrlIsAbsolute(): void
    {
        touch($this->testDir . '/content/404.md');

        [, $display] = $this->audit('https://example.com/sub/');

        $this->assertStringNotContainsString(self::WARNING, $display);
    }

    public function testDoesNotWarnWhenNo404FileExists(): void
    {
        [, $display] = $this->audit('/sub/');

        $this->assertStringNotContainsString(self::WARNING, $display);
    }

    public function testWarningDoesNotChangeExitCode(): void
    {
        [$cleanExit] = $this->audit('https://example.com/');

        touch($this->testDir . '/content/404.md');
        [$warnedExit, $display] = $this->audit('/sub/');

        $this->assertStringContainsString(self::WARNING, $display);
        $this->assertSame($cleanExit, $warnedExit);
    }
}
