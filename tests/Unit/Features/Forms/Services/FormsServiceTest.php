<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\Forms\Services;

use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Features\Forms\Services\FormsService;
use EICC\Utils\Container;
use EICC\Utils\Log;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\LoaderInterface;

class FormsServiceTest extends TestCase
{
    private FormsService $service;
    private Log&MockObject $logger;
    private Container&MockObject $container;
    private Environment&MockObject $twig;
    private LoaderInterface&MockObject $twigLoader;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(Log::class);
        $this->container = $this->createMock(Container::class);
        $this->twig = $this->createMock(Environment::class);
        $this->twigLoader = $this->createMock(LoaderInterface::class);

        $this->twig->method('getLoader')->willReturn($this->twigLoader);

        $this->service = new FormsService($this->logger, $this->twig, $this->container);
    }

    private function makeEvent(string $filePath = '', string $fileContent = ''): RenderEvent
    {
        return new RenderEvent(
            name: 'RENDER',
            filePath: $filePath,
            fileUrl: '',
            metadata: [],
            extra: $fileContent !== '' ? ['file_content' => $fileContent] : [],
        );
    }

    public function testGenerateFormHtmlDefaultTemplate(): void
    {
        $config = [
            'provider_url' => 'https://api.example.com/submit',
            'form_id' => '123',
            'fields' => []
        ];

        $this->twig->expects($this->once())
            ->method('render')
            ->with('staticforce/_form.html.twig', $this->callback(function ($context) {
                return $context['endpoint'] === 'https://api.example.com/submit?FORMID=123';
            }))
            ->willReturn('<form>Default</form>');

        $html = $this->service->generateFormHtml($config, 'default');
        $this->assertEquals('<form>Default</form>', $html);
    }

    public function testGenerateFormHtmlCustomTemplate(): void
    {
        $config = [
            'provider_url' => 'https://api.example.com/submit',
            'form_id' => '123',
            'template' => 'custom_form'
        ];

        $this->twigLoader->method('exists')->willReturn(true);

        $this->twig->expects($this->once())
            ->method('render')
            ->with('default/custom_form.html.twig', $this->anything())
            ->willReturn('<form>Custom</form>');

        $html = $this->service->generateFormHtml($config, 'default');
        $this->assertEquals('<form>Custom</form>', $html);
    }

    public function testGenerateFormHtmlCustomTemplateFallback(): void
    {
        $config = [
            'provider_url' => 'https://api.example.com/submit',
            'form_id' => '123',
            'template' => 'missing_form'
        ];

        $this->twigLoader->method('exists')->willReturn(false);

        $this->logger->expects($this->once())->method('log')->with('WARNING', $this->stringContains('not found'));

        $this->twig->expects($this->once())
            ->method('render')
            ->with('staticforce/_form.html.twig', $this->anything())
            ->willReturn('<form>Default</form>');

        $html = $this->service->generateFormHtml($config, 'default');
        $this->assertEquals('<form>Default</form>', $html);
    }

    public function testProcessFormsReplacesShortcode(): void
    {
        $content = 'Content before {{ form("contact") }} Content after';
        $event = $this->makeEvent(fileContent: $content);

        $siteConfig = [
            'forms' => [
                'contact' => [
                    'provider_url' => 'url',
                    'form_id' => '1'
                ]
            ]
        ];

        $this->container->method('getVariable')
            ->willReturnMap([
                ['site_config', $siteConfig],
                ['TEMPLATE', 'default']
            ]);

        $this->twig->method('render')->willReturn('<form>Contact Form</form>');

        $this->service->processForms($event);

        $this->assertEquals(
            'Content before <form>Contact Form</form> Content after',
            $event->extra['file_content']
        );
    }

    public function testProcessFormsIgnoresUnknownForm(): void
    {
        $content = '{{ form("unknown") }}';
        $event = $this->makeEvent(fileContent: $content);

        $this->container->method('getVariable')->willReturn([]);

        $this->logger->expects($this->once())->method('log')->with('WARNING', $this->stringContains('not found'));

        $this->service->processForms($event);

        // Content should remain unchanged if form not found (or at least shortcode remains, logic says continue)
        $this->assertEquals($content, $event->extra['file_content']);
    }

    public function testProcessFormsReturnsParametersWhenNoContentAndNoFilePath(): void
    {
        $event = $this->makeEvent();

        $this->expectNotToPerformAssertions();
        $this->service->processForms($event);
    }

    public function testProcessFormsThrowsWhenSourceDirNotSetAndFileGiven(): void
    {
        $filePath = sys_get_temp_dir() . '/staticforge_forms_test_' . uniqid() . '.md';
        file_put_contents($filePath, 'content');

        $this->container->method('getVariable')->willReturn(null);

        $event = $this->makeEvent(filePath: $filePath);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SOURCE_DIR not set in container');

        try {
            $this->service->processForms($event);
        } finally {
            unlink($filePath);
        }
    }

    public function testProcessFormsThrowsWhenFileOutsideSourceDir(): void
    {
        $sourceDir = sys_get_temp_dir() . '/staticforge_forms_source_' . uniqid();
        mkdir($sourceDir, 0755, true);

        $outsideFile = sys_get_temp_dir() . '/staticforge_forms_outside_' . uniqid() . '.md';
        file_put_contents($outsideFile, 'content');

        $this->container->method('getVariable')
            ->willReturnMap([
                ['SOURCE_DIR', $sourceDir],
            ]);

        $event = $this->makeEvent(filePath: $outsideFile);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Security Error/');

        try {
            $this->service->processForms($event);
        } finally {
            unlink($outsideFile);
            rmdir($sourceDir);
        }
    }

    public function testGenerateFormHtmlDropsNonHttpsProviderUrl(): void
    {
        $config = [
            'provider_url' => 'http://api.example.com/submit',
            'form_id' => '123',
        ];

        $this->logger->expects($this->once())
            ->method('log')
            ->with('WARNING', $this->stringContains('provider_url'));

        $this->twig->expects($this->once())
            ->method('render')
            ->with('staticforce/_form.html.twig', $this->callback(function ($context) {
                return $context['endpoint'] === '';
            }))
            ->willReturn('<form></form>');

        $this->service->generateFormHtml($config, 'default');
    }

    public function testGenerateFormHtmlDropsNonHttpsChallengeUrl(): void
    {
        $config = [
            'provider_url' => 'https://api.example.com/submit',
            'form_id' => '123',
            'challenge_url' => 'javascript:alert(1)',
        ];

        $this->logger->expects($this->once())
            ->method('log')
            ->with('WARNING', $this->stringContains('challenge_url'));

        $this->twig->expects($this->once())
            ->method('render')
            ->with('staticforce/_form.html.twig', $this->callback(function ($context) {
                return $context['challenge_url'] === null;
            }))
            ->willReturn('<form></form>');

        $this->service->generateFormHtml($config, 'default');
    }

    public function testGenerateFormHtmlKeepsHttpsChallengeUrl(): void
    {
        $config = [
            'provider_url' => 'https://api.example.com/submit',
            'form_id' => '123',
            'challenge_url' => 'https://altcha.example.com/challenge',
        ];

        $this->twig->expects($this->once())
            ->method('render')
            ->with('staticforce/_form.html.twig', $this->callback(function ($context) {
                return $context['challenge_url'] === 'https://altcha.example.com/challenge';
            }))
            ->willReturn('<form></form>');

        $this->service->generateFormHtml($config, 'default');
    }

    public function testGenerateFormHtmlAppendsFormIdWithAmpersandWhenQueryParamsPresent(): void
    {
        $config = [
            'provider_url' => 'https://api.example.com/submit?existing=1',
            'form_id' => '123',
        ];

        $this->twig->expects($this->once())
            ->method('render')
            ->with('staticforce/_form.html.twig', $this->callback(function ($context) {
                return $context['endpoint'] === 'https://api.example.com/submit?existing=1&FORMID=123';
            }))
            ->willReturn('<form>Default</form>');

        $this->service->generateFormHtml($config, 'default');
    }
}
