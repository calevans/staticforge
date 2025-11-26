<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Services\Upload;

use EICC\StaticForge\Services\Upload\SftpConfigLoader;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

class SftpConfigLoaderTest extends UnitTestCase
{
    private SftpConfigLoader $loader;
    private InputDefinition $definition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loader = new SftpConfigLoader();
        $this->definition = new InputDefinition([
            new InputOption('input', null, InputOption::VALUE_REQUIRED),
        ]);
    }

    public function testLoadConfigurationWithMissingOutputDir(): void
    {
        $this->setContainerVariable('OUTPUT_DIR', '');
        $input = new ArrayInput([], $this->definition);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No input directory specified');

        $this->loader->load($input, $this->container);
    }

    public function testLoadConfigurationWithNonExistentDirectory(): void
    {
        $this->setContainerVariable('OUTPUT_DIR', '/nonexistent/directory');
        $input = new ArrayInput([], $this->definition);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Input directory does not exist');

        $this->loader->load($input, $this->container);
    }

    public function testLoadConfigurationWithMissingHost(): void
    {
        $this->setContainerVariable('OUTPUT_DIR', __DIR__);
        $this->setContainerVariable('SFTP_HOST', '');
        $input = new ArrayInput([], $this->definition);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SFTP_HOST not configured');

        $this->loader->load($input, $this->container);
    }

    public function testLoadConfigurationWithMissingUsername(): void
    {
        $this->setContainerVariable('OUTPUT_DIR', __DIR__);
        $this->setContainerVariable('SFTP_HOST', 'example.com');
        $this->setContainerVariable('SFTP_USERNAME', '');
        $input = new ArrayInput([], $this->definition);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SFTP_USERNAME not configured');

        $this->loader->load($input, $this->container);
    }

    public function testLoadConfigurationWithMissingRemotePath(): void
    {
        $this->setContainerVariable('OUTPUT_DIR', __DIR__);
        $this->setContainerVariable('SFTP_HOST', 'example.com');
        $this->setContainerVariable('SFTP_USERNAME', 'user');
        $this->setContainerVariable('SFTP_REMOTE_PATH', '');
        $input = new ArrayInput([], $this->definition);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SFTP_REMOTE_PATH not configured');

        $this->loader->load($input, $this->container);
    }

    public function testLoadConfigurationWithMissingAuth(): void
    {
        $this->setContainerVariable('OUTPUT_DIR', __DIR__);
        $this->setContainerVariable('SFTP_HOST', 'example.com');
        $this->setContainerVariable('SFTP_USERNAME', 'user');
        $this->setContainerVariable('SFTP_REMOTE_PATH', '/var/www');
        $this->setContainerVariable('SFTP_PASSWORD', '');
        $this->setContainerVariable('SFTP_PRIVATE_KEY_PATH', '');
        $input = new ArrayInput([], $this->definition);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Either SFTP_PASSWORD or SFTP_PRIVATE_KEY_PATH must be configured');

        $this->loader->load($input, $this->container);
    }

    public function testLoadConfigurationWithValidPasswordAuth(): void
    {
        $this->setContainerVariable('OUTPUT_DIR', __DIR__);
        $this->setContainerVariable('SFTP_HOST', 'example.com');
        $this->setContainerVariable('SFTP_USERNAME', 'user');
        $this->setContainerVariable('SFTP_REMOTE_PATH', '/var/www');
        $this->setContainerVariable('SFTP_PASSWORD', 'secret');
        $input = new ArrayInput([], $this->definition);

        $config = $this->loader->load($input, $this->container);

        $this->assertEquals(__DIR__, $config['input_dir']);
        $this->assertEquals('example.com', $config['host']);
        $this->assertEquals('user', $config['username']);
        $this->assertEquals('/var/www', $config['remote_path']);
        $this->assertEquals('secret', $config['password']);
    }

    public function testLoadConfigurationWithInputOverride(): void
    {
        $this->setContainerVariable('OUTPUT_DIR', '/tmp'); // Should be ignored
        $this->setContainerVariable('SFTP_HOST', 'example.com');
        $this->setContainerVariable('SFTP_USERNAME', 'user');
        $this->setContainerVariable('SFTP_REMOTE_PATH', '/var/www');
        $this->setContainerVariable('SFTP_PASSWORD', 'secret');

        $input = new ArrayInput(['--input' => __DIR__], $this->definition);

        $config = $this->loader->load($input, $this->container);

        $this->assertEquals(__DIR__, $config['input_dir']);
    }
}
