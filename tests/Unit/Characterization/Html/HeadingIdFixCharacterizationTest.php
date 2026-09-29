<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Characterization\Html;

use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Features\MarkdownRenderer\ContentExtractor;
use EICC\StaticForge\Features\MarkdownRenderer\MarkdownProcessor;
use EICC\StaticForge\Features\MarkdownRenderer\Services\MarkdownRendererService;
use EICC\StaticForge\Services\TemplateRenderer;
use EICC\Utils\Container;
use EICC\Utils\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Freezes the 3.3.7 output of MarkdownRendererService::fixHeadingIds for the shared HTML corpus.
 *
 * fixHeadingIds is private. This is characterization of a soon-to-be-replaced internal, so it is
 * invoked via reflection; the public processMarkdownFile path needs a full renderer stack and adds
 * nothing to what is being pinned (the DOM round trip).
 */
class HeadingIdFixCharacterizationTest extends TestCase
{
    use GoldenAssertions;

    private const GROUP = 'heading_ids';

    /**
     * @return array<string, array{string}>
     */
    public static function corpusProvider(): array
    {
        return HtmlCorpus::asProvider();
    }

    private function fixHeadingIds(string $html): string
    {
        $service = new MarkdownRendererService(
            $this->createStub(Log::class),
            $this->createStub(MarkdownProcessor::class),
            $this->createStub(ContentExtractor::class),
            $this->createStub(TemplateRenderer::class),
            $this->createStub(EventManager::class),
            new Container()
        );

        $method = new ReflectionMethod($service, 'fixHeadingIds');
        $result = $method->invoke($service, $html);
        $this->assertIsString($result);

        return $result;
    }

    #[DataProvider('corpusProvider')]
    public function testFixHeadingIdsOutputMatchesFrozenGolden(string $case): void
    {
        $this->assertMatchesGolden(self::GROUP, $case, $this->fixHeadingIds(HtmlCorpus::cases()[$case]));
    }

    public function testGoldenSetMatchesCorpus(): void
    {
        $this->assertNoOrphanedGoldens(self::GROUP, array_keys(HtmlCorpus::cases()));
    }
}
