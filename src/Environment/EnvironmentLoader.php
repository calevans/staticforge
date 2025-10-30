<?php

namespace EICC\StaticForge\Environment;

use EICC\Utils\Container;
use EICC\Utils\Log;
use Dotenv\Dotenv;
use InvalidArgumentException;

/**
 * Loads environment configuration and validates required variables
 */
class EnvironmentLoader
{
    private Container $container;

    /**
     * Required environment variables
     * @var array<string>
     */
    private array $requiredVariables = [
        'SITE_NAME',
        'SITE_BASE_URL',
        'SOURCE_DIR',
        'OUTPUT_DIR',
        'TEMPLATE_DIR',
        'FEATURES_DIR'
    ];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Load environment file and populate container
     */
    public function load(string $envPath = '.env'): void
    {
        if (!file_exists($envPath)) {
            throw new InvalidArgumentException("Environment file not found: {$envPath}");
        }

        // Check if this is a virtual filesystem path (for testing)
        if (strpos($envPath, 'vfs://') === 0) {
            // Manual parsing for virtual filesystem to avoid Dotenv issues
            $content = file_get_contents($envPath);
            if ($content === false) {
                throw new InvalidArgumentException("Failed to read environment file: {$envPath}");
            }

            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, '#') === 0) {
                    continue;
                }
                if (strpos($line, '=') !== false) {
                    [$key, $value] = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value, '"\'');
                    $_ENV[$key] = $value;
                }
            }
        } else {
            // Use Dotenv for real filesystem
            $dotenv = Dotenv::createUnsafeImmutable(dirname($envPath), basename($envPath));
            $dotenv->load();
        }

        $this->validateRequiredVariables();
        $this->populateContainer();
        $this->initializeLogger();
    }

    /**
     * Validate all required environment variables are present
     */
    private function validateRequiredVariables(): void
    {
        $missing = [];

        foreach ($this->requiredVariables as $variable) {
            if (!isset($_ENV[$variable]) || empty($_ENV[$variable])) {
                $missing[] = $variable;
            }
        }

        if (!empty($missing)) {
            throw new InvalidArgumentException(
                'Missing required environment variables: ' . implode(', ', $missing)
            );
        }
    }

    /**
     * Push all environment variables into container
     */
    private function populateContainer(): void
    {
        foreach ($_ENV as $key => $value) {
            $this->container->setVariable($key, $value);
        }
    }

    /**
     * Add additional required variables for validation
     */
    public function addRequiredVariable(string $variable): void
    {
        if (!in_array($variable, $this->requiredVariables)) {
            $this->requiredVariables[] = $variable;
        }
    }

    /**
     * Initialize logger from environment configuration
     */
    private function initializeLogger(): void
    {
        $logFile = $this->container->getVariable('LOG_FILE') ?? 'logs/staticforge.log';
        $logLevel = $this->container->getVariable('LOG_LEVEL') ?? 'INFO';

        // Ensure logs directory exists
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            if (!mkdir($logDir, 0755, true)) {
                throw new InvalidArgumentException("Cannot create log directory: {$logDir}");
            }
        }

        // Create logger instance
        $logger = new Log('staticforge', $logFile, $logLevel);
        $this->container->setVariable('logger', $logger);
    }
}
