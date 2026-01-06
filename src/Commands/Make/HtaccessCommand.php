<?php

declare(strict_types=1);

namespace EICC\StaticForge\Commands\Make;

use EICC\Utils\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class HtaccessCommand extends Command
{
    protected static $defaultName = 'make:htaccess';
    protected static $defaultDescription = 'Generate a production-ready .htaccess file';

    protected Container $container;
    protected SymfonyStyle $io;

    public function __construct(Container $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function configure(): void
    {
        $this->setDescription('Generate a production-ready .htaccess file')
            ->addOption('write', 'w', InputOption::VALUE_NONE, 'Write the output to a file (default: htaccess.txt)')
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'The output filename', 'htaccess.txt');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        // Base Content
        $content = <<<'EOT'
# ----------------------------------------------------------------------
# 1. Protect StaticForge Manifest
# ----------------------------------------------------------------------
<Files "staticforge-manifest.json">
    Require all denied
</Files>

# ----------------------------------------------------------------------
# 2. Security Headers
# ----------------------------------------------------------------------
<IfModule mod_headers.c>
    # HSTS (Strict-Transport-Security)
    # Tells browser to ONLY use HTTPS for the next year.
    # We use 'always set' to ensure it applies to redirect responses too.
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"

    # Prevent MIME-Type Sniffing
    Header always set X-Content-Type-Options "nosniff"

    # Prevent Clickjacking
    Header always set X-Frame-Options "SAMEORIGIN"

    # Content Security Policy (CSP)
    Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' https://www.google-analytics.com https://www.googletagmanager.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:;"
</IfModule>

# ----------------------------------------------------------------------
# 3. Performance: Browser Caching
# ----------------------------------------------------------------------
<IfModule mod_headers.c>
    # Default: Cache HTML/Data for 1 hour (3600 seconds)
    # Using 'set' instead of 'always set' to avoid caching error pages (404/500)
    Header set Cache-Control "max-age=3600, public"

    # Assets: Cache CSS, JS, Images, Fonts for 1 week
    <FilesMatch "\.(ico|pdf|flv|jpg|jpeg|png|gif|svg|webp|js|css|woff|woff2|ttf)$">
        Header set Cache-Control "max-age=604800, public"
    </FilesMatch>
</IfModule>
EOT;

        if ($input->getOption('write')) {
            $file = $input->getOption('output');

            // Safety check: warn if overwriting .htaccess directly, though user explicitly asked for a tool to do this.
            if ($file === '.htaccess' && file_exists('.htaccess')) {
                // If they passed --write and -o .htaccess, we probably shouldn't block them, but a note is polite.
                $this->io->note("Overwriting existing .htaccess file.");
            }

            if (file_put_contents($file, $content) !== false) {
                $this->io->success("Generated .htaccess content to {$file}");
                return Command::SUCCESS;
            } else {
                $this->io->error("Failed to write to {$file}");
                return Command::FAILURE;
            }
        } else {
            // Just print to screen
            $output->writeln($content);
            $this->io->newLine();
            $this->io->text('<info>Tip:</info> Use <comment>--write</comment> to save this content to <comment>htaccess.txt</comment>');
            return Command::SUCCESS;
        }
    }
}
