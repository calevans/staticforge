<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\Forms\Services;

use EICC\StaticForge\Features\Forms\Services\FormsService;
use EICC\Utils\Container;
use EICC\Utils\Log;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\LoaderInterface;

class FormsServiceTest extends TestCase
{
    private FormsService $service;
    private Log $logger;
    private Container $container;
    private Environment $twig;
    private LoaderInterface $twigLoader;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(Log::class);
        $this->container = $this->createMock(Container::class);
        $this->twig = $this->createMock(Environment::class);
        $this->twigLoader = $this->createMock(LoaderInterface::class);

        $this->twig->method('getLoader')->willReturn($this->twigLoader);

        $this->service = new FormsService($this->logger, $this->twig);
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
            ->with('staticforce/_form.html.twig', $this->callback(function($context) {
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
        $parameters = ['file_content' => $content];

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

        $result = $this->service->processForms($this->container, $parameters);

        $this->assertEquals('Content before <form>Contact Form</form> Content after', $result['file_content']);
    }

    public function testProcessFormsIgnoresUnknownForm(): void
    {
        $content = '{{ form("unknown") }}';
        $parameters = ['file_content' => $content];

        $this->container->method('getVariable')->willReturn([]);

        $this->logger->expects($this->once())->method('log')->with('WARNING', $this->stringContains('not found'));

        $result = $this->service->processForms($this->container, $parameters);

        // Content should remain unchanged if form not found (or at least shortcode remains, logic says continue)
        $this->assertEquals($content, $result['file_content']);
    }
}
