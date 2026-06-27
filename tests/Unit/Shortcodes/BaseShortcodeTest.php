<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Shortcodes;

use EICC\StaticForge\Features\MarkdownRenderer\MarkdownProcessor;
use EICC\StaticForge\Services\TemplateRenderer;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class BaseShortcodeTest extends UnitTestCase
{
    public function testRenderThrowsWhenTemplateRendererNotInitialized(): void
    {
        $shortcode = $this->createConcreteShortcode();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('TemplateRenderer or Container not initialized in Shortcode');

        $shortcode->publicRender('shortcodes/whatever.twig', []);
    }

    public function testRenderThrowsWhenContainerNotInitialized(): void
    {
        $shortcode = $this->createConcreteShortcode();
        /** @var TemplateRenderer&MockObject $renderer */
        $renderer = $this->createMock(TemplateRenderer::class);
        $shortcode->setTemplateRenderer($renderer);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('TemplateRenderer or Container not initialized in Shortcode');

        $shortcode->publicRender('shortcodes/whatever.twig', []);
    }

    public function testRenderDelegatesToTemplateRendererWhenInitialized(): void
    {
        /** @var TemplateRenderer&MockObject $renderer */
        $renderer = $this->createMock(TemplateRenderer::class);
        $renderer->expects($this->once())
            ->method('renderTemplate')
            ->with('shortcodes/whatever.twig', ['foo' => 'bar'], $this->container)
            ->willReturn('<rendered/>');

        $shortcode = $this->createConcreteShortcode();
        $shortcode->setTemplateRenderer($renderer);
        $shortcode->setContainer($this->container);

        $result = $shortcode->publicRender('shortcodes/whatever.twig', ['foo' => 'bar']);

        $this->assertSame('<rendered/>', $result);
    }

    public function testProcessMarkdownLazilyCreatesProcessorWhenNotSet(): void
    {
        $shortcode = $this->createConcreteShortcode();

        $html = $shortcode->publicProcessMarkdown('**bold text**');

        $this->assertStringContainsString('<strong>bold text</strong>', $html);
    }

    public function testProcessMarkdownUsesInjectedProcessor(): void
    {
        /** @var MarkdownProcessor&MockObject $processor */
        $processor = $this->createMock(MarkdownProcessor::class);
        $processor->expects($this->once())
            ->method('convert')
            ->with('raw')
            ->willReturn('CONVERTED');

        $shortcode = $this->createConcreteShortcode();
        $shortcode->setMarkdownProcessor($processor);

        $this->assertSame('CONVERTED', $shortcode->publicProcessMarkdown('raw'));
    }

    public function testConcreteShortcodeImplementsInterfaceContract(): void
    {
        $shortcode = $this->createConcreteShortcode();

        $this->assertSame('test', $shortcode->getName());
        $this->assertSame('', $shortcode->handle([]));
    }

    private function createConcreteShortcode(): ConcreteTestShortcode
    {
        return new ConcreteTestShortcode();
    }
}

/**
 * Named test double for BaseShortcode that exposes its protected helpers publicly
 * so tests can exercise them directly. Declared as a named (not anonymous) class so
 * PHPStan can see the extra public methods through the return type of the factory above.
 */
class ConcreteTestShortcode extends \EICC\StaticForge\Shortcodes\BaseShortcode
{
    public function getName(): string
    {
        return 'test';
    }

    public function handle(array $attributes, string $content = ''): string
    {
        return '';
    }

    /**
     * @param array<string, mixed> $variables
     */
    public function publicRender(string $template, array $variables = []): string
    {
        return $this->render($template, $variables);
    }

    public function publicProcessMarkdown(string $content): string
    {
        return $this->processMarkdown($content);
    }
}
