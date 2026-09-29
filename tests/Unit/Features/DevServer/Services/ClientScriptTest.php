<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\DevServer\Services;

use EICC\StaticForge\Features\DevServer\Services\ClientScript;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Static checks only: the script is never executed (no JS runtime in this project).
 */
class ClientScriptTest extends TestCase
{
    private function js(): string
    {
        $tag = ClientScript::tag();
        $this->assertStringStartsWith('<script>', $tag);
        $this->assertStringEndsWith('</script>', $tag);

        return substr($tag, 8, -9);
    }

    public function testTagIsDeterministicAndContainsExactlyOneScriptElement(): void
    {
        $this->assertSame(ClientScript::tag(), ClientScript::tag());
        $this->assertSame(1, substr_count(strtolower(ClientScript::tag()), '<script'));
        $this->assertSame(1, substr_count(strtolower(ClientScript::tag()), '</script'));
    }

    public function testScriptTextIsSetThroughTextContent(): void
    {
        $this->assertGreaterThanOrEqual(5, substr_count($this->js(), '.textContent'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function forbiddenApiProvider(): array
    {
        return [
            'innerHTML' => ['innerHTML'],
            'outerHTML' => ['outerHTML'],
            'insertAdjacentHTML' => ['insertAdjacentHTML'],
            'document.write' => ['document.write'],
            'eval' => ['eval('],
            'Function constructor' => ['new Function'],
            'string timer' => ['setTimeout("'],
            'string timer single quote' => ["setTimeout('"],
            'console.log' => ['console.log'],
            'console.error' => ['console.error'],
            'debugger' => ['debugger'],
            'external url' => ['http://'],
            'external https url' => ['https://'],
            'localStorage' => ['localStorage'],
            'cookie' => ['document.cookie'],
        ];
    }

    #[DataProvider('forbiddenApiProvider')]
    public function testScriptAvoidsUnsafeOrNoisyApis(string $needle): void
    {
        $this->assertStringNotContainsString($needle, $this->js());
    }

    public function testAllTopLevelIdentifiersInsideTheClosureAreSfdevPrefixed(): void
    {
        $js = $this->js();

        preg_match_all('/^  function (\w+)/m', $js, $functions);
        preg_match_all('/^  var (.+?);$/m', $js, $varLines);
        $vars = [];
        foreach ($varLines[1] as $line) {
            preg_match_all('/(?:^|, )(\w+)(?: = |$)/', $line, $names);
            array_push($vars, ...$names[1]);
        }

        $this->assertNotEmpty($functions[1]);
        $this->assertNotEmpty($vars);
        foreach ([...$functions[1], ...$vars] as $name) {
            $this->assertStringStartsWith('sfdev', $name);
        }
    }

    public function testOnlySfdevPropertiesAreAddedToWindow(): void
    {
        preg_match_all('/window\.(\w+)/', $this->js(), $matches);

        $this->assertContains('sfdevLoaded', $matches[1]);
        foreach ($matches[1] as $property) {
            $this->assertTrue(
                str_starts_with($property, 'sfdev') || in_array($property, ['scrollTo', 'scrollY'], true),
                $property
            );
        }
    }

    public function testStorageKeyAndElementIdentifiersArePrefixed(): void
    {
        $js = $this->js();

        $this->assertStringContainsString("'sfdevScrollY'", $js);
        $this->assertStringNotContainsString('.id =', $js);
        $this->assertStringNotContainsString('getElementById', $js);
    }

    public function testScrollPositionIsPreservedAcrossReloadsViaSessionStorage(): void
    {
        $js = $this->js();

        $this->assertStringContainsString('sessionStorage.setItem', $js);
        $this->assertStringContainsString('sessionStorage.getItem', $js);
        $this->assertStringContainsString('sessionStorage.removeItem', $js);
        $this->assertStringContainsString('window.scrollTo', $js);
        $this->assertStringContainsString('window.scrollY', $js);
        $this->assertLessThan(strpos($js, 'location.reload'), strpos($js, 'sessionStorage.setItem'));
    }

    public function testStatusIndicatorIsAPoliteLiveRegion(): void
    {
        $js = $this->js();

        $this->assertStringContainsString("setAttribute('role', 'status')", $js);
        $this->assertStringContainsString("setAttribute('aria-live', 'polite')", $js);
        $this->assertStringContainsString('Rebuilding', $js);
    }

    public function testFailureBannerIsAnAlertWithDismissAndFailureText(): void
    {
        $js = $this->js();

        $this->assertStringContainsString("setAttribute('role', 'alert')", $js);
        $this->assertStringContainsString('Build failed - showing last good version', $js);
        $this->assertStringContainsString("'Dismiss'", $js);
    }

    public function testUiIsRenderedInAnOpenShadowRoot(): void
    {
        $this->assertStringContainsString("attachShadow({ mode: 'open' })", $this->js());
    }

    public function testPollingUsesNoStoreStateEndpointBacksOffAndSkipsHiddenTabs(): void
    {
        $js = $this->js();

        $this->assertStringContainsString("fetch('/__staticforge/state', { cache: 'no-store' })", $js);
        $this->assertStringContainsString('document.hidden', $js);
        $this->assertStringContainsString('sfdevDelay = 5000', $js);
        $this->assertStringContainsString('Dev server disconnected', $js);
        $this->assertStringContainsString('sfdevDelay = 1000', $js);
    }

    public function testReloadIsDeferredWhileAFormFieldHasFocus(): void
    {
        $js = $this->js();

        $this->assertStringContainsString('INPUT|TEXTAREA|SELECT', $js);
        $this->assertStringContainsString('isContentEditable', $js);
        $this->assertStringContainsString("addEventListener('focusout'", $js);
    }

    public function testScriptIsGuardedAgainstBeingLoadedTwice(): void
    {
        $js = $this->js();

        $this->assertStringContainsString('if (window.sfdevLoaded) { return; }', $js);
    }

    public function testBaselineVersionIsRecordedWithoutReloading(): void
    {
        $this->assertStringContainsString('if (sfdevVer === null) { sfdevVer = s.v; }', $this->js());
    }

    public function testScriptContainsNoTemplatePlaceholdersOrServerInterpolation(): void
    {
        $js = $this->js();

        $this->assertStringNotContainsString('%%', $js);
        $this->assertStringNotContainsString('<?', $js);
        $this->assertStringNotContainsString('{{', $js);
    }
}
