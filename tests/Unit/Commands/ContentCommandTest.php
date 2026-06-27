<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Commands;

use EICC\StaticForge\Commands\Audit\ContentCommand;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class ContentCommandTest extends UnitTestCase
{
    private string $testDir;
    private string $originalCwd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCwd = (string)getcwd();
        $this->testDir = sys_get_temp_dir() . '/staticforge_content_test_' . uniqid();
        mkdir($this->testDir);
        mkdir($this->testDir . '/content');
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
        $application->add(new ContentCommand($this->container));
        $command = $application->find('audit:content');

        return new CommandTester($command);
    }

    public function testFailsWhenContentDirectoryMissing(): void
    {
        rmdir($this->testDir . '/content');

        $tester = $this->makeCommandTester();
        $exitCode = $tester->execute([]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Content directory not found', $tester->getDisplay());
    }

    public function testPassesWithValidFrontmatterAndLinks(): void
    {
        file_put_contents($this->testDir . '/content/post.md', <<<MD
---
title: Hello World
---

# Hello

[Link to self](post.md)
MD);

        $tester = $this->makeCommandTester();
        $exitCode = $tester->execute([]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Content audit passed', $tester->getDisplay());
    }

    public function testFailsWhenFrontmatterMissing(): void
    {
        file_put_contents($this->testDir . '/content/post.md', "# Hello\n\nNo frontmatter here.");

        $tester = $this->makeCommandTester();
        $exitCode = $tester->execute([]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Missing or invalid frontmatter block', $tester->getDisplay());
    }

    public function testFailsWhenRequiredTitleFieldMissing(): void
    {
        file_put_contents($this->testDir . '/content/post.md', <<<MD
---
date: 2024-01-01
---

# Hello
MD);

        $tester = $this->makeCommandTester();
        $exitCode = $tester->execute([]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Missing required field: title', $tester->getDisplay());
    }

    public function testFailsOnInvalidYamlFrontmatter(): void
    {
        file_put_contents($this->testDir . '/content/post.md', <<<MD
---
title: [unterminated
---

# Hello
MD);

        $tester = $this->makeCommandTester();
        $exitCode = $tester->execute([]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('YAML Parse Error', $tester->getDisplay());
    }

    public function testWarnsWhenPostIsMarkedDraft(): void
    {
        file_put_contents($this->testDir . '/content/post.md', <<<MD
---
title: Hello World
draft: true
---

# Hello
MD);

        $tester = $this->makeCommandTester();
        $exitCode = $tester->execute([]);

        // Draft is a warning, not an error, so exit code should still be success
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Post is marked as draft', $tester->getDisplay());
    }

    public function testFailsWhenMarkdownLinkTargetMissing(): void
    {
        file_put_contents($this->testDir . '/content/post.md', <<<MD
---
title: Hello World
---

[Broken link](does-not-exist.md)
MD);

        $tester = $this->makeCommandTester();
        $exitCode = $tester->execute([]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Link target not found: does-not-exist.md', $tester->getDisplay());
    }

    public function testIgnoresExternalAndAnchorAndHtmlLinks(): void
    {
        file_put_contents($this->testDir . '/content/post.md', <<<MD
---
title: Hello World
---

[External](https://example.com)
[Anchor](#section)
[Mailto](mailto:test@example.com)
[Output link](/some-page.html)
MD);

        $tester = $this->makeCommandTester();
        $exitCode = $tester->execute([]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Content audit passed', $tester->getDisplay());
    }
}
