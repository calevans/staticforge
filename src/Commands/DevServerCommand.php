<?php

declare(strict_types=1);

namespace EICC\StaticForge\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'site:devserver',
    description: 'Start development server with proper 404 handling'
)]
class DevServerCommand extends Command
{
    private string $routerFile;
    private string $publicDir;
    private string $projectRoot;
    private ?int $serverPid = null;

    protected function configure(): void
    {
        $this
            ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'Port to serve on', '8000')
            ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'Host to bind to', 'localhost')
            ->setHelp('This command starts a development server with proper 404 handling for static files.');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->publicDir = getcwd() . '/public';
        $this->routerFile = $this->publicDir . '/.ht.route.php';

        // Register cleanup function
        register_shutdown_function([$this, 'cleanup']);

        // Handle signals for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $host = $input->getOption('host');
        $port = (int) $input->getOption('port');

        // Check if public directory exists
        if (!is_dir($this->publicDir)) {
            $io->error("Public directory not found: {$this->publicDir}");
            $io->note('Run "php bin/console.php render:site" first to generate the site.');
            return Command::FAILURE;
        }

        // Check if port is available
        if ($this->isPortInUse($host, $port)) {
            $io->error("Port {$port} is already in use on {$host}");
            return Command::FAILURE;
        }

        try {
            // Create router file
            $this->createRouterFile();

            $io->success("Development server starting...");
            $io->info("Server: http://{$host}:{$port}");
            $io->info("Document root: {$this->publicDir}");
            $io->warning("Press Ctrl+C to stop the server");
            $io->newLine();

            // Start the server
            $this->startServer($host, $port, $io);
        } catch (\Exception $e) {
            $io->error("Failed to start server: " . $e->getMessage());
            $this->cleanup();
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function createRouterFile(): void
    {
        $routerContent = $this->getRouterTemplate();

        if (file_put_contents($this->routerFile, $routerContent) === false) {
            throw new \RuntimeException("Failed to create router file: {$this->routerFile}");
        }
    }

    private function startServer(string $host, int $port, SymfonyStyle $io): void
    {
        $command = sprintf(
            'php -S %s:%d -t %s %s 2>&1',
            escapeshellarg($host),
            $port,
            escapeshellarg($this->publicDir),
            escapeshellarg('.ht.route.php')
        );

        // Change to public directory for the server
        $oldCwd = getcwd();
        chdir($this->publicDir);

        $process = popen($command, 'r');

        if (!$process) {
            chdir($oldCwd);
            throw new \RuntimeException("Failed to start PHP server");
        }

        // Read server output and display
        while (!feof($process)) {
            $line = fgets($process);
            if ($line !== false) {
                $io->text(trim($line));
            }

            // Allow signal handling
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            usleep(100000); // 100ms
        }

        pclose($process);
        chdir($oldCwd);
    }

    private function isPortInUse(string $host, int $port): bool
    {
        $socket = @fsockopen($host, $port, $errno, $errstr, 1);
        if ($socket) {
            fclose($socket);
            return true;
        }
        return false;
    }

    public function handleSignal(int $signal): void
    {
        echo "\nShutting down development server...\n";
        $this->cleanup();
        exit(0);
    }

    public function cleanup(): void
    {
        if (file_exists($this->routerFile)) {
            unlink($this->routerFile);
        }
    }

    private function getRouterTemplate(): string
    {
        return '<?php
/**
 * Development router for StaticForge
 * Handles proper 404 responses for non-existent static files
 * 
 * WARNING: This file is automatically generated and managed by the site:devserver command.
 * Do not edit manually - changes will be lost when the server restarts.
 */

$requestUri = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$filePath = __DIR__ . $requestUri;

// If the file exists, let the server handle it normally
if (is_file($filePath)) {
    return false; // Let PHP serve the file
}

// If it\'s a directory, check for index.html
if (is_dir($filePath)) {
    $indexFile = rtrim($filePath, "/") . "/index.html";
    if (is_file($indexFile)) {
        return false; // Let PHP serve the directory
    }
}

// File doesn\'t exist - return proper 404
http_response_code(404);
header("Content-Type: text/html; charset=UTF-8");

$escapedUri = htmlspecialchars($requestUri, ENT_QUOTES, "UTF-8");

echo \'<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found | Static Forge</title>
    <style>
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 50px 20px;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            max-width: 600px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }
        h1 {
            font-size: 4rem;
            margin: 0 0 20px 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        h2 {
            font-size: 1.5rem;
            margin: 0 0 30px 0;
            opacity: 0.9;
        }
        p {
            font-size: 1.1rem;
            line-height: 1.6;
            opacity: 0.8;
            margin-bottom: 20px;
        }
        .url {
            font-family: monospace;
            background: rgba(0, 0, 0, 0.2);
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.9rem;
            word-break: break-all;
        }
        a {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 25px;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.3);
            margin-top: 20px;
        }
        a:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
        }
        .dev-note {
            font-size: 0.9rem;
            opacity: 0.6;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>404</h1>
        <h2>Page Not Found</h2>
        <p>The page <span class="url">\' . $escapedUri . \'</span> could not be found.</p>
        <p>It may have been moved, deleted, or you may have entered the wrong URL.</p>
        <a href="/">← Back to Home</a>
        <div class="dev-note">
            <strong>Development Mode:</strong> This 404 page is served by the StaticForge development server.
        </div>
    </div>
</body>
</html>\';

exit;
';
    }
}
