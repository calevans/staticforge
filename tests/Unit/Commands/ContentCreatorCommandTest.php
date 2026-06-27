<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Commands;

use EICC\StaticForge\Commands\Make\ContentCreatorCommand;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ContentCreatorCommandTest extends UnitTestCase
{
    private string $testDir;
    private string $originalCwd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCwd = (string)getcwd();
        $this->testDir = sys_get_temp_dir() . '/staticforge_content_creator_test_' . uniqid();
        mkdir($this->testDir);
        chdir($this->testDir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        $this->removeDirectory($this->testDir);
        parent::tearDown();
    }

    private function makeCommandTester(): CommandTester
    {
        $application = new Application();
        $application->add(new ContentCreatorCommand($this->container));
        $command = $application->find('make:content');

        return new CommandTester($command);
    }

    public function testCreatesContentFileWithBasicFrontmatter(): void
    {
        $tester = $this->makeCommandTester();
        $exitCode = $tester->execute(['title' => 'My First Post']);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($this->testDir . '/content/my-first-post.md');

        $content = file_get_contents($this->testDir . '/content/my-first-post.md');
        $this->assertNotFalse($content);
        $this->assertStringContainsString('title: "My First Post"', $content);
        $this->assertStringContainsString('# My First Post', $content);
    }

    public function testCreatesContentFileInTypeSubfolder(): void
    {
        $tester = $this->makeCommandTester();
        $exitCode = $tester->execute(['title' => 'Blog Entry', '--type' => 'blog']);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($this->testDir . '/content/blog/blog-entry.md');

        $content = file_get_contents($this->testDir . '/content/blog/blog-entry.md');
        $this->assertNotFalse($content);
        $this->assertStringContainsString('category: "blog"', $content);
    }

    public function testCreatesContentFileMarkedAsDraft(): void
    {
        $tester = $this->makeCommandTester();
        $exitCode = $tester->execute(['title' => 'Draft Post', '--draft' => true]);

        $this->assertSame(0, $exitCode);
        $content = file_get_contents($this->testDir . '/content/draft-post.md');
        $this->assertNotFalse($content);
        $this->assertStringContainsString('draft: true', $content);
    }

    public function testUsesProvidedDate(): void
    {
        $tester = $this->makeCommandTester();
        $exitCode = $tester->execute(['title' => 'Dated Post', '--date' => '2024-06-15']);

        $this->assertSame(0, $exitCode);
        $content = file_get_contents($this->testDir . '/content/dated-post.md');
        $this->assertNotFalse($content);
        $this->assertStringContainsString('date: "2024-06-15"', $content);
    }

    public function testFailsWhenFileAlreadyExists(): void
    {
        mkdir($this->testDir . '/content');
        file_put_contents($this->testDir . '/content/existing-post.md', 'existing content');

        $tester = $this->makeCommandTester();
        $exitCode = $tester->execute(['title' => 'Existing Post']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('already exists', $tester->getDisplay());

        // Original content must be left untouched
        $this->assertSame('existing content', file_get_contents($this->testDir . '/content/existing-post.md'));
    }

    public function testSlugifiesTitleWithSpecialCharacters(): void
    {
        $tester = $this->makeCommandTester();
        $exitCode = $tester->execute(['title' => 'Héllo Wörld! 100% Awesome?']);

        $this->assertSame(0, $exitCode);
        $files = glob($this->testDir . '/content/*.md') ?: [];
        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+\.md$/', basename($files[0]));
    }

    public function testFallsBackToUntitledWhenSlugIsEmpty(): void
    {
        $tester = $this->makeCommandTester();
        $exitCode = $tester->execute(['title' => '!!!???###']);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($this->testDir . '/content/untitled.md');
    }

    public function testCreatesTargetDirectoryWhenMissing(): void
    {
        $this->assertDirectoryDoesNotExist($this->testDir . '/content/docs');

        $tester = $this->makeCommandTester();
        $exitCode = $tester->execute(['title' => 'Doc Page', '--type' => 'docs']);

        $this->assertSame(0, $exitCode);
        $this->assertDirectoryExists($this->testDir . '/content/docs');
    }
}
