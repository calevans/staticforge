<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Services\Upload;

use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Services\Upload\SiteUploader;
use EICC\StaticForge\Services\Upload\UploadCheckService;
use EICC\StaticForge\Services\Upload\UploadOptions;
use EICC\StaticForge\Tests\Mocks\InMemorySftpClient;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use EICC\Utils\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * The server's .htaccess is only ever appended to: manifest protection and the 404 ErrorDocument line.
 */
class SiteUploaderHtaccessTest extends UnitTestCase
{
    private const HTACCESS = '/remote/.htaccess';

    private string $localDir;
    private InMemorySftpClient $client;
    private SiteUploader $uploader;
    private BufferedOutput $output;

    protected function setUp(): void
    {
        parent::setUp();
        $this->localDir = sys_get_temp_dir() . '/staticforge_htaccess_test_' . uniqid();
        mkdir($this->localDir);
        file_put_contents($this->localDir . '/index.html', 'home');
        $this->client = new InMemorySftpClient();
        $this->output = new BufferedOutput();
        $logger = $this->container->get('logger');
        $this->assertInstanceOf(Log::class, $logger);
        $this->uploader = new SiteUploader($this->client, $logger, new UploadCheckService(), new EventManager());
    }

    protected function tearDown(): void
    {
        foreach (glob($this->localDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->localDir);
        parent::tearDown();
    }

    private function upload(?string $errorDocumentPath, bool $dryRun = false): int
    {
        return $this->uploader->upload(
            $this->localDir,
            '/remote',
            $dryRun,
            $this->output,
            new UploadOptions(errorDocumentPath: $errorDocumentPath)
        );
    }

    public function testAppendsErrorDocumentAndKeepsExistingRules(): void
    {
        $this->client->files[self::HTACCESS] = "# my rules\nHeader always set X-Test \"1\"\n";

        $this->assertSame(0, $this->upload('/sub/404.html'));

        $written = $this->client->files[self::HTACCESS];
        $this->assertStringStartsWith("# my rules\nHeader always set X-Test \"1\"\n", $written);
        $this->assertSame(1, substr_count($written, 'ErrorDocument 404 /sub/404.html'));
        $this->assertStringContainsString('Require all denied', $written);
        $this->assertStringContainsString('ErrorDocument 404 /sub/404.html', $this->output->fetch());
    }

    public function testCreatesHtaccessWithBothBlocksWhenMissing(): void
    {
        $this->upload('/404.html');

        $written = $this->client->files[self::HTACCESS];
        $this->assertStringStartsWith('<Files', $written);
        $this->assertStringContainsString('ErrorDocument 404 /404.html', $written);
        $this->assertStringContainsString('staticforge-manifest.json', $written);
    }

    public function testSecondRunDoesNotAddTheLineAgain(): void
    {
        $this->client->files[self::HTACCESS] = "# mine\n";
        $this->upload('/404.html');
        $afterFirst = $this->client->files[self::HTACCESS];

        $this->upload('/404.html');

        $this->assertSame($afterFirst, $this->client->files[self::HTACCESS]);
        $this->assertSame(1, substr_count($afterFirst, 'ErrorDocument 404'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function existingDirectiveProvider(): array
    {
        return [
            'own page' => ["ErrorDocument 404 /errors/notfound.html\n"],
            'indented' => ["  ErrorDocument 404 /x.html\n"],
            'lower case' => ["errordocument 404 /x.html\n"],
            'full url' => ["ErrorDocument 404 https://example.com/x\n"],
            'inline text' => ["ErrorDocument 404 \"Gone\"\n"],
        ];
    }

    #[DataProvider('existingDirectiveProvider')]
    public function testNeverOverridesAnExistingErrorDocument404(string $existing): void
    {
        $this->client->files[self::HTACCESS] = $existing;

        $this->upload('/404.html');

        $this->assertSame(0, strpos($this->client->files[self::HTACCESS], $existing));
        $this->assertSame(1, preg_match_all('/ErrorDocument 404/i', $this->client->files[self::HTACCESS]));
    }

    public function testACommentedOutLineDoesNotCountAsExisting(): void
    {
        $this->client->files[self::HTACCESS] = "# ErrorDocument 404 /old.html\n";

        $this->upload('/404.html');

        $this->assertStringContainsString("\nErrorDocument 404 /404.html\n", $this->client->files[self::HTACCESS]);
    }

    public function testOtherErrorCodesDoNotBlockTheLine(): void
    {
        $this->client->files[self::HTACCESS] = "ErrorDocument 500 /500.html\nErrorDocument 4040 /x\n";

        $this->upload('/404.html');

        $this->assertStringContainsString('ErrorDocument 404 /404.html', $this->client->files[self::HTACCESS]);
    }

    public function testNoLineWhenSiteHasNo404Page(): void
    {
        $this->client->files[self::HTACCESS] = "# mine\n";

        $this->upload(null);

        $this->assertStringNotContainsString('ErrorDocument', $this->client->files[self::HTACCESS]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unsafePathProvider(): array
    {
        return [
            'full url' => ['https://evil.example/404.html'],
            'newline injection' => ["/404.html\nHeader set X y"],
            'space' => ['/a b/404.html'],
            'not the 404 page' => ['/secret.html'],
            'relative' => ['404.html'],
            'quote' => ['/a"/404.html'],
            'protocol relative' => ['//host/404.html'],
            'dot dot' => ['/a/../404.html'],
            'trailing newline' => ["/404.html\n"],
            'longer name' => ['/not404.html'],
        ];
    }

    #[DataProvider('unsafePathProvider')]
    public function testRefusesUnsafeOrUnexpectedPaths(string $path): void
    {
        $this->client->files[self::HTACCESS] = "# mine\n";

        $this->upload($path);

        $this->assertStringNotContainsString('ErrorDocument', $this->client->files[self::HTACCESS]);
    }

    public function testDryRunWritesNothing(): void
    {
        $this->client->files[self::HTACCESS] = "# mine\n";

        $this->upload('/404.html', true);

        $this->assertSame("# mine\n", $this->client->files[self::HTACCESS]);
    }

    public function testFailedUploadLeavesHtaccessAlone(): void
    {
        $this->client->files[self::HTACCESS] = "# mine\n";
        $this->client->failUploadNumber = 1;

        $this->assertSame(1, $this->upload('/404.html'));

        $this->assertSame("# mine\n", $this->client->files[self::HTACCESS]);
    }

    public function testFileWithoutTrailingNewlineKeepsItsLastRule(): void
    {
        $this->client->files[self::HTACCESS] = 'Options -Indexes';

        $this->upload('/404.html');

        $written = $this->client->files[self::HTACCESS];
        $this->assertStringStartsWith("Options -Indexes\n", $written);
        $this->assertStringContainsString("\nErrorDocument 404 /404.html\n", $written);
    }

    public function testCrLfFileWithErrorDocumentIsRecognised(): void
    {
        $existing = "# mine\r\nErrorDocument 404 /x.html\r\n";
        $this->client->files[self::HTACCESS] = $existing;

        $this->upload('/404.html');

        $this->assertSame(1, preg_match_all('/ErrorDocument 404/', $this->client->files[self::HTACCESS]));
    }

    public function testErrorDocumentInsideABlockCountsAsExisting(): void
    {
        $existing = "<IfModule mod_rewrite.c>\nErrorDocument 404 /x.html\n</IfModule>\n";
        $this->client->files[self::HTACCESS] = $existing;

        $this->upload('/404.html');

        $this->assertSame(1, preg_match_all('/ErrorDocument 404/', $this->client->files[self::HTACCESS]));
    }

    public function testManifestBlockIsNotDuplicatedWhenOnlyTheErrorDocumentIsMissing(): void
    {
        $this->client->files[self::HTACCESS] =
            "<Files \"staticforge-manifest.json\">\n    Require all denied\n</Files>\n";

        $this->upload('/404.html');

        $written = $this->client->files[self::HTACCESS];
        $this->assertSame(1, substr_count($written, 'staticforge-manifest.json'));
        $this->assertSame(1, substr_count($written, 'ErrorDocument 404 /404.html'));
    }

    public function testUnsafePathPrintsAWarningAndWritesNoErrorDocument(): void
    {
        $this->client->files[self::HTACCESS] = "# mine\n";

        $this->upload('/a b/404.html');

        $this->assertStringContainsString('Not adding an ErrorDocument line', $this->output->fetch());
        $this->assertStringNotContainsString('ErrorDocument', $this->client->files[self::HTACCESS]);
    }

    /**
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function urlProvider(): array
    {
        return [
            'root' => ['https://example.com/', '/404.html'],
            'no path' => ['https://example.com', '/404.html'],
            'sub path' => ['https://example.com/docs', '/docs/404.html'],
            'sub path trailing slash' => ['https://example.com/docs/', '/docs/404.html'],
            'nested' => ['https://example.com/a/b/', '/a/b/404.html'],
            'space' => ['https://example.com/my docs/', null],
            'encoded space' => ['https://example.com/my%20docs/', null],
            'dot dot' => ['https://example.com/a/../b/', null],
            'unparseable' => ['http:///', null],
        ];
    }

    #[DataProvider('urlProvider')]
    public function testErrorDocumentPathIsDerivedFromTheSiteUrl(string $url, ?string $expected): void
    {
        $this->assertSame($expected, SiteUploader::errorDocumentPathForUrl($url));
    }
}
