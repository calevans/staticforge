<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Commands;

use EICC\StaticForge\Commands\UploadSiteCommand;
use EICC\Utils\Container;
use EICC\Utils\Log;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class UploadSiteCommandTest extends UnitTestCase
{

  protected Log $logger;

  protected function setUp(): void
  {
    parent::setUp();

    $this->logger = $this->container->get('logger');
  }

  public function testCommandConfiguration(): void
  {
    $command = new UploadSiteCommand($this->container);

    $this->assertEquals('site:upload', $command->getName());
    $this->assertStringContainsString('Upload generated static site', $command->getDescription());
    $this->assertTrue($command->getDefinition()->hasOption('input'));
  }

  public function testLoadConfigurationWithMissingOutputDir(): void
  {
    // Clear OUTPUT_DIR to test validation
    $this->setContainerVariable('OUTPUT_DIR', '');

    $command = new UploadSiteCommand($this->container);
    $input = new ArrayInput([]);
    $input->bind($command->getDefinition());

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('No input directory specified');

    $method = new \ReflectionMethod($command, 'loadConfiguration');
    $method->setAccessible(true);
    $method->invoke($command, $input);
  }

  public function testLoadConfigurationWithNonExistentDirectory(): void
  {
    $this->setContainerVariable('OUTPUT_DIR', '/nonexistent/directory');

    $command = new UploadSiteCommand($this->container);
    $input = new ArrayInput([]);
    $input->bind($command->getDefinition());

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Input directory does not exist');

    $method = new \ReflectionMethod($command, 'loadConfiguration');
    $method->setAccessible(true);
    $method->invoke($command, $input);
  }

  public function testLoadConfigurationWithMissingHost(): void
  {
    $this->setContainerVariable('OUTPUT_DIR', __DIR__);
    $this->setContainerVariable('SFTP_HOST', ''); // Clear to test validation

    $command = new UploadSiteCommand($this->container);
    $input = new ArrayInput([]);
    $input->bind($command->getDefinition());

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('SFTP_HOST not configured');

    $method = new \ReflectionMethod($command, 'loadConfiguration');
    $method->setAccessible(true);
    $method->invoke($command, $input);
  }

  public function testLoadConfigurationWithMissingUsername(): void
  {
    $this->setContainerVariable('OUTPUT_DIR', __DIR__);
    $this->setContainerVariable('SFTP_HOST', 'example.com');
    $this->setContainerVariable('SFTP_USERNAME', ''); // Clear to test validation

    $command = new UploadSiteCommand($this->container);
    $input = new ArrayInput([]);
    $input->bind($command->getDefinition());

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('SFTP_USERNAME not configured');

    $method = new \ReflectionMethod($command, 'loadConfiguration');
    $method->setAccessible(true);
    $method->invoke($command, $input);
  }

  public function testLoadConfigurationWithMissingRemotePath(): void
  {
    $this->setContainerVariable('OUTPUT_DIR', __DIR__);
    $this->setContainerVariable('SFTP_HOST', 'example.com');
    $this->setContainerVariable('SFTP_USERNAME', 'testuser');
    $this->setContainerVariable('SFTP_REMOTE_PATH', ''); // Clear to test validation

    $command = new UploadSiteCommand($this->container);
    $input = new ArrayInput([]);
    $input->bind($command->getDefinition());

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('SFTP_REMOTE_PATH not configured');

    $method = new \ReflectionMethod($command, 'loadConfiguration');
    $method->setAccessible(true);
    $method->invoke($command, $input);
  }

  public function testLoadConfigurationWithMissingAuthentication(): void
  {
    $this->setContainerVariable('OUTPUT_DIR', __DIR__);
    $this->setContainerVariable('SFTP_HOST', 'example.com');
    $this->setContainerVariable('SFTP_USERNAME', 'testuser');
    $this->setContainerVariable('SFTP_REMOTE_PATH', '/var/www/html');
    $this->setContainerVariable('SFTP_PASSWORD', ''); // Clear both auth methods
    $this->setContainerVariable('SFTP_PRIVATE_KEY_PATH', '');

    $command = new UploadSiteCommand($this->container);
    $input = new ArrayInput([]);
    $input->bind($command->getDefinition());

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Either SFTP_PASSWORD or SFTP_PRIVATE_KEY_PATH must be configured');

    $method = new \ReflectionMethod($command, 'loadConfiguration');
    $method->setAccessible(true);
    $method->invoke($command, $input);
  }

  public function testLoadConfigurationWithValidPasswordAuth(): void
  {
    $this->setContainerVariable('OUTPUT_DIR', __DIR__);
    $this->setContainerVariable('SFTP_HOST', 'example.com');
    $this->setContainerVariable('SFTP_PORT', '2222');
    $this->setContainerVariable('SFTP_USERNAME', 'testuser');
    $this->setContainerVariable('SFTP_PASSWORD', 'testpass');
    $this->setContainerVariable('SFTP_REMOTE_PATH', '/var/www/html/');

    $command = new UploadSiteCommand($this->container);
    $input = new ArrayInput([]);
    $input->bind($command->getDefinition());

    $method = new \ReflectionMethod($command, 'loadConfiguration');
    $method->setAccessible(true);
    $config = $method->invoke($command, $input);

    $this->assertEquals(__DIR__, $config['input_dir']);
    $this->assertEquals('example.com', $config['host']);
    $this->assertEquals(2222, $config['port']);
    $this->assertEquals('testuser', $config['username']);
    $this->assertEquals('testpass', $config['password']);
    $this->assertEquals('/var/www/html', $config['remote_path']); // Trailing slash removed
  }

  public function testLoadConfigurationWithValidKeyAuth(): void
  {
    $this->setContainerVariable('OUTPUT_DIR', __DIR__);
    $this->setContainerVariable('SFTP_HOST', 'example.com');
    $this->setContainerVariable('SFTP_USERNAME', 'testuser');
    $this->setContainerVariable('SFTP_PRIVATE_KEY_PATH', '/home/user/.ssh/id_rsa');
    $this->setContainerVariable('SFTP_PRIVATE_KEY_PASSPHRASE', 'keypass');
    $this->setContainerVariable('SFTP_REMOTE_PATH', '/var/www/html');

    $command = new UploadSiteCommand($this->container);
    $input = new ArrayInput([]);
    $input->bind($command->getDefinition());

    $method = new \ReflectionMethod($command, 'loadConfiguration');
    $method->setAccessible(true);
    $config = $method->invoke($command, $input);

    $this->assertEquals('/home/user/.ssh/id_rsa', $config['key_path']);
    $this->assertEquals('keypass', $config['key_passphrase']);
  }

  public function testLoadConfigurationWithInputOverride(): void
  {
    $this->setContainerVariable('OUTPUT_DIR', '/default/path');
    $this->setContainerVariable('SFTP_HOST', 'example.com');
    $this->setContainerVariable('SFTP_USERNAME', 'testuser');
    $this->setContainerVariable('SFTP_PASSWORD', 'testpass');
    $this->setContainerVariable('SFTP_REMOTE_PATH', '/var/www/html');

    $command = new UploadSiteCommand($this->container);
    $input = new ArrayInput(['--input' => __DIR__]);
    $input->bind($command->getDefinition());

    $method = new \ReflectionMethod($command, 'loadConfiguration');
    $method->setAccessible(true);
    $config = $method->invoke($command, $input);

    $this->assertEquals(__DIR__, $config['input_dir']);
  }

  public function testGetFilesToUpload(): void
  {
    // Create a temporary directory structure
    $testDir = sys_get_temp_dir() . '/sftp_test_' . uniqid();
    mkdir($testDir);
    mkdir($testDir . '/subdir');
    file_put_contents($testDir . '/file1.txt', 'test');
    file_put_contents($testDir . '/subdir/file2.txt', 'test');

    $command = new UploadSiteCommand($this->container);

    $method = new \ReflectionMethod($command, 'getFilesToUpload');
    $method->setAccessible(true);
    $files = $method->invoke($command, $testDir);

    $this->assertCount(2, $files);
    $this->assertContains($testDir . '/file1.txt', $files);
    $this->assertContains($testDir . '/subdir/file2.txt', $files);

    // Clean up
    unlink($testDir . '/file1.txt');
    unlink($testDir . '/subdir/file2.txt');
    rmdir($testDir . '/subdir');
    rmdir($testDir);
  }

  public function testGetFilesToUploadWithEmptyDirectory(): void
  {
    $testDir = sys_get_temp_dir() . '/sftp_test_empty_' . uniqid();
    mkdir($testDir);

    $command = new UploadSiteCommand($this->container);

    $method = new \ReflectionMethod($command, 'getFilesToUpload');
    $method->setAccessible(true);
    $files = $method->invoke($command, $testDir);

    $this->assertCount(0, $files);

    // Clean up
    rmdir($testDir);
  }

  public function testDisconnect(): void
  {
    $command = new UploadSiteCommand($this->container);

    $method = new \ReflectionMethod($command, 'disconnect');
    $method->setAccessible(true);

    // Should not throw exception even if not connected
    $method->invoke($command);

    $this->assertTrue(true); // If we got here, no exception was thrown
  }
}
