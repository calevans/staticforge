<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Shortcodes;

use EICC\StaticForge\Services\TemplateRenderer;
use EICC\StaticForge\Shortcodes\BaseShortcode;
use EICC\StaticForge\Shortcodes\ShortcodeInterface;
use EICC\StaticForge\Shortcodes\ShortcodeManager;
use EICC\StaticForge\Tests\Unit\UnitTestCase;

class ShortcodeManagerTest extends UnitTestCase
{
    private ShortcodeManager $manager;
    private TemplateRenderer $templateRenderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->templateRenderer = $this->createMock(TemplateRenderer::class);
        $this->manager = new ShortcodeManager($this->container, $this->templateRenderer);
    }

    public function testProcessReturnsContentUncahngedWhenNoShortcodesRegistered(): void
    {
        $content = 'Plain text with no shortcodes';
        $result = $this->manager->process($content);

        $this->assertSame($content, $result);
    }

    public function testProcessReplacesRegisteredSelfClosingShortcode(): void
    {
        $shortcode = $this->createSimpleShortcode('greet', 'Hello!');
        $this->manager->register($shortcode);

        $result = $this->manager->process('Before [[greet]] After');

        $this->assertSame('Before Hello! After', $result);
    }

    public function testProcessLeavesUnregisteredShortcodeUntouched(): void
    {
        $result = $this->manager->process('Text [[unknown id="1"]] more text');

        $this->assertSame('Text [[unknown id="1"]] more text', $result);
    }

    public function testProcessPassesAttributesToShortcode(): void
    {
        $capturedAttributes = null;

        $shortcode = $this->createMock(ShortcodeInterface::class);
        $shortcode->method('getName')->willReturn('box');
        $shortcode->method('handle')
            ->willReturnCallback(function (array $attributes, string $content = '') use (&$capturedAttributes) {
                $capturedAttributes = $attributes;
                return 'BOX';
            });

        $this->manager->register($shortcode);
        $this->manager->process('[[box type="warning" id=\'42\' flag]]');

        $this->assertSame([
            'type' => 'warning',
            'id' => '42',
            'flag' => 'true',
        ], $capturedAttributes);
    }

    public function testProcessPassesInnerContentToEnclosingShortcode(): void
    {
        $capturedContent = null;

        $shortcode = $this->createMock(ShortcodeInterface::class);
        $shortcode->method('getName')->willReturn('wrap');
        $shortcode->method('handle')
            ->willReturnCallback(function (array $attributes, string $content = '') use (&$capturedContent) {
                $capturedContent = $content;
                return "<wrapped>{$content}</wrapped>";
            });

        $this->manager->register($shortcode);
        $result = $this->manager->process('[[wrap]]Inner Text[[/wrap]]');

        $this->assertSame('Inner Text', $capturedContent);
        $this->assertSame('<wrapped>Inner Text</wrapped>', $result);
    }

    public function testProcessHandlesEscapedShortcodeSyntax(): void
    {
        $shortcode = $this->createSimpleShortcode('greet', 'Hello!');
        $this->manager->register($shortcode);

        // Triple brackets escape the shortcode so it is rendered literally (one layer stripped)
        $result = $this->manager->process('[[[greet]]]');

        $this->assertSame('[[greet]]', $result);
    }

    public function testProcessCatchesShortcodeExceptionAndReturnsErrorComment(): void
    {
        $shortcode = $this->createMock(ShortcodeInterface::class);
        $shortcode->method('getName')->willReturn('broken');
        $shortcode->method('handle')->willThrowException(new \RuntimeException('boom'));

        $this->manager->register($shortcode);
        $result = $this->manager->process('[[broken]]');

        $this->assertSame('<!-- Shortcode error: broken -->', $result);
    }

    public function testRegisterInjectsDependenciesIntoBaseShortcode(): void
    {
        $shortcode = new class extends BaseShortcode {
            public function getName(): string
            {
                return 'inspect';
            }

            public function handle(array $attributes, string $content = ''): string
            {
                return ($this->templateRenderer !== null && $this->container !== null) ? 'INJECTED' : 'MISSING';
            }
        };

        $this->manager->register($shortcode);
        $result = $this->manager->process('[[inspect]]');

        $this->assertSame('INJECTED', $result);
    }

    private function createSimpleShortcode(string $name, string $output): ShortcodeInterface
    {
        $shortcode = $this->createMock(ShortcodeInterface::class);
        $shortcode->method('getName')->willReturn($name);
        $shortcode->method('handle')->willReturn($output);

        return $shortcode;
    }
}
