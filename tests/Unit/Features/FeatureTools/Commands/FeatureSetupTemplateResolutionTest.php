<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Features\FeatureTools\Commands;

use EICC\StaticForge\Features\FeatureTools\Commands\FeatureSetupCommand;
use EICC\StaticForge\Tests\Unit\UnitTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Regression tests for feature:setup resolving the active template.
 *
 * bootstrap.php resolves TEMPLATE as siteconfig site.template > .env TEMPLATE >
 * 'staticforce' and stores the winner in the container. It also unconditionally
 * defaults $_ENV['TEMPLATE'] to 'staticforce' before siteconfig is read, so a
 * command that trusts $_ENV lands partials in templates/staticforce/ while the
 * site renders from templates/<siteconfig template>/.
 *
 * These tests therefore never set $_ENV['TEMPLATE']: doing so would pass against
 * the buggy code and prove nothing.
 */
class FeatureSetupTemplateResolutionTest extends UnitTestCase
{
    private string $tempCwd;
    private string $originalCwd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalCwd = (string) getcwd();
        $this->tempCwd = sys_get_temp_dir() . '/staticforge_setup_tpl_' . uniqid('', true);
        mkdir($this->tempCwd . '/vendor', 0755, true);
        chdir($this->tempCwd);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        $this->removeDirectory($this->tempCwd);
        parent::tearDown();
    }

    /**
     * @param array<int, string> $exampleFiles
     */
    private function makePackage(string $packageName, array $exampleFiles): string
    {
        $packageDir = $this->tempCwd . '/vendor/' . $packageName;
        mkdir($packageDir, 0755, true);

        foreach ($exampleFiles as $file) {
            file_put_contents($packageDir . '/' . $file, "partial body\n");
        }

        return $packageDir;
    }

    private function runSetup(string $packageName): CommandTester
    {
        $application = new Application();
        $application->addCommand(new FeatureSetupCommand($this->container));

        $tester = new CommandTester($application->find('feature:setup'));
        $tester->execute(['package' => $packageName]);

        return $tester;
    }

    public function testTwigPartialLandsInSiteconfigTemplateDirectory(): void
    {
        // The template is set ONLY in the container, exactly as bootstrap would
        // set it from siteconfig's site.template. $_ENV['TEMPLATE'] is untouched.
        $this->setContainerVariable('TEMPLATE', 'mytheme');
        $this->setContainerVariable('TEMPLATE_DIR', $this->tempCwd . '/templates');
        mkdir($this->tempCwd . '/templates/mytheme', 0755, true);

        $this->makePackage('calevans/staticforge-podcast', ['_podcast_badges.html.twig.example']);

        $tester = $this->runSetup('calevans/staticforge-podcast');

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());

        $this->assertFileExists(
            $this->tempCwd . '/templates/mytheme/_podcast_badges.html.twig',
            'Partial must land in the template the site actually renders from'
        );
        $this->assertFileDoesNotExist(
            $this->tempCwd . '/templates/staticforce/_podcast_badges.html.twig',
            'Partial must not land in the hardcoded default template'
        );
    }

    public function testExampleSuffixIsStrippedSoTwigCanFindThePartial(): void
    {
        $this->setContainerVariable('TEMPLATE', 'mytheme');
        $this->setContainerVariable('TEMPLATE_DIR', $this->tempCwd . '/templates');
        mkdir($this->tempCwd . '/templates/mytheme', 0755, true);

        $this->makePackage('calevans/staticforge-podcast', ['_podcast_badges.html.twig.example']);
        $this->runSetup('calevans/staticforge-podcast');

        $this->assertFileExists(
            $this->tempCwd . '/templates/mytheme/_podcast_badges.html.twig',
            'The stripped partial must be written'
        );
        $this->assertFileDoesNotExist(
            $this->tempCwd . '/templates/mytheme/_podcast_badges.html.twig.example',
            'The .example suffix must not survive the copy'
        );
    }

    public function testResolvedDestinationIsPrintedBeforeCopying(): void
    {
        $this->setContainerVariable('TEMPLATE', 'mytheme');
        $this->setContainerVariable('TEMPLATE_DIR', $this->tempCwd . '/templates');
        mkdir($this->tempCwd . '/templates/mytheme', 0755, true);

        $this->makePackage('calevans/staticforge-podcast', ['_podcast_badges.html.twig.example']);
        $display = $this->runSetup('calevans/staticforge-podcast')->getDisplay();

        $this->assertStringContainsString('mytheme', $display);
        $this->assertStringContainsString($this->tempCwd . '/templates/mytheme', $display);
    }

    public function testFailsLoudlyWhenTemplateDirectoryDoesNotExist(): void
    {
        $this->setContainerVariable('TEMPLATE', 'ghosttheme');
        $this->setContainerVariable('TEMPLATE_DIR', $this->tempCwd . '/templates');
        // Deliberately do not create templates/ghosttheme.

        $this->makePackage('calevans/staticforge-podcast', ['_podcast_badges.html.twig.example']);
        $tester = $this->runSetup('calevans/staticforge-podcast');

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('does not exist', $tester->getDisplay());
        $this->assertDirectoryDoesNotExist(
            $this->tempCwd . '/templates/ghosttheme',
            'A missing theme must not be silently created'
        );
    }

    public function testExistingPartialIsNotOverwritten(): void
    {
        $this->setContainerVariable('TEMPLATE', 'mytheme');
        $this->setContainerVariable('TEMPLATE_DIR', $this->tempCwd . '/templates');
        mkdir($this->tempCwd . '/templates/mytheme', 0755, true);

        $existing = $this->tempCwd . '/templates/mytheme/_podcast_badges.html.twig';
        file_put_contents($existing, "user edits\n");

        $this->makePackage('calevans/staticforge-podcast', ['_podcast_badges.html.twig.example']);
        $this->runSetup('calevans/staticforge-podcast');

        $this->assertSame("user edits\n", file_get_contents($existing));
    }

    public function testCssExampleSuffixIsStrippedIntoSourceDir(): void
    {
        $this->setContainerVariable('TEMPLATE', 'mytheme');
        $this->setContainerVariable('TEMPLATE_DIR', $this->tempCwd . '/templates');
        $this->setContainerVariable('SOURCE_DIR', $this->tempCwd . '/content');
        mkdir($this->tempCwd . '/content', 0755, true);

        $this->makePackage('calevans/staticforge-podcast', ['podcast.css.example']);
        $tester = $this->runSetup('calevans/staticforge-podcast');

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertFileExists($this->tempCwd . '/content/assets/css/podcast.css');
    }

    public function testMergeableConfigExamplesKeepTheirSuffix(): void
    {
        $this->setContainerVariable('TEMPLATE', 'mytheme');
        $this->setContainerVariable('TEMPLATE_DIR', $this->tempCwd . '/templates');

        $this->makePackage('calevans/staticforge-podcast', ['siteconfig.yaml.example', '.env.example']);
        $this->runSetup('calevans/staticforge-podcast');

        // Unlike drop-in files, these are fragments to merge by hand.
        $this->assertFileExists($this->tempCwd . '/siteconfig.yaml.example.staticforge-podcast');
        $this->assertFileExists($this->tempCwd . '/.env.example.staticforge-podcast');
    }
}
