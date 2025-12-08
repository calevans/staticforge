<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Commands;

use EICC\StaticForge\Commands\InspectMediaCommand;
use EICC\StaticForge\Services\MediaInspector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class InspectMediaCommandTest extends TestCase
{
    private string $testDir;
    private string $markdownFile;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/sf_inspect_test_' . uniqid();
        mkdir($this->testDir);

        $this->markdownFile = $this->testDir . '/episode.md';
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->testDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testExecuteFailsIfFileDoesNotExist(): void
    {
        $application = new Application();
        $application->add(new InspectMediaCommand());
        $command = $application->find('media:inspect');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['file' => '/non/existent/file.md']);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('File not found', $output);
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    public function testExecuteFailsIfNoFrontmatter(): void
    {
        file_put_contents($this->markdownFile, "# Just Markdown\nNo frontmatter here.");

        $application = new Application();
        $application->add(new InspectMediaCommand());
        $command = $application->find('media:inspect');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['file' => $this->markdownFile]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('No valid YAML frontmatter found', $output);
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    public function testExecuteFailsIfNoMediaFileInFrontmatter(): void
    {
        $content = "---\ntitle: Test Episode\n---\n# Content";
        file_put_contents($this->markdownFile, $content);

        $application = new Application();
        $application->add(new InspectMediaCommand());
        $command = $application->find('media:inspect');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['file' => $this->markdownFile]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString("No 'audio_file' or 'video_file' found", $output);
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    public function testExecuteFailsIfLocalMediaNotFound(): void
    {
        $content = "---\ntitle: Test Episode\naudio_file: /missing/audio.mp3\n---\n# Content";
        file_put_contents($this->markdownFile, $content);

        $application = new Application();
        $application->add(new InspectMediaCommand());
        $command = $application->find('media:inspect');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['file' => $this->markdownFile]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Local media file not found', $output);
        $this->assertEquals(1, $commandTester->getStatusCode());
    }

    public function testExecuteUpdatesFrontmatterWhenFileExists(): void
    {
        // Create dummy media file
        $mediaFile = $this->testDir . '/audio.mp3';
        file_put_contents($mediaFile, 'dummy audio content');

        // Create markdown file referencing it using absolute path
        $content = "---\ntitle: Test Episode\naudio_file: {$mediaFile}\n---\n# Content";
        file_put_contents($this->markdownFile, $content);

        // Mock MediaInspector
        $inspectorMock = $this->createMock(MediaInspector::class);
        $inspectorMock->method('inspect')
            ->willReturn([
                'size' => 1024,
                'type' => 'audio/mpeg',
                'duration' => '00:01:30'
            ]);

        $application = new Application();
        $application->add(new InspectMediaCommand($inspectorMock));
        $command = $application->find('media:inspect');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['file' => $this->markdownFile]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Analysis Complete', $output);
        $this->assertStringContainsString('Updated ' . $this->markdownFile, $output);
        $this->assertEquals(0, $commandTester->getStatusCode());

        // Verify file content
        $updatedContent = file_get_contents($this->markdownFile);
        $this->assertStringContainsString('audio_size: 1024', $updatedContent);
        $this->assertStringContainsString('audio_type: audio/mpeg', $updatedContent);
        $this->assertStringContainsString('itunes_duration: \'00:01:30\'', $updatedContent);
    }
}
