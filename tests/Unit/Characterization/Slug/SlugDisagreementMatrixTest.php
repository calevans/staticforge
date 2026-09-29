<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Characterization\Slug;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Documents, as data (SlugCorpus::disagreements), which corpus inputs are slugged differently by
 * the six implementations. The headline cases (design 11.4): "Dr.Who" and "a&b" become "dr-who" /
 * "a-b" for category directories but "drwho" / "ab" in FileDiscovery::slugify, and an empty or
 * symbol-only category is '' in three implementations, 'category' in RSS and 'untitled' in
 * ContentCreator.
 */
class SlugDisagreementMatrixTest extends TestCase
{
    private string $originalLocale = 'C';

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalLocale = SlugImplementations::currentLocale();
        if (SlugImplementations::pinLocale() === null) {
            $this->markTestSkipped('No C.UTF-8 locale; ContentCreator output (and so the matrix) is locale dependent.');
        }
    }

    protected function tearDown(): void
    {
        SlugImplementations::restoreLocale($this->originalLocale);
        parent::tearDown();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function corpusNamesProvider(): array
    {
        $out = [];
        foreach (array_keys(SlugCorpus::cases()) as $name) {
            $out[$name] = [$name];
        }

        return $out;
    }

    /**
     * @return array<string, list<list<string>>>
     */
    private function liveGroups(string $input): array
    {
        $byOutput = [];
        foreach (SlugImplementations::ALL as $implementation) {
            $byOutput[SlugImplementations::slug($implementation, $input)][] = $implementation;
        }

        return array_values($byOutput) === [] ? [] : ['groups' => array_values($byOutput)];
    }

    #[DataProvider('corpusNamesProvider')]
    public function testAgreementGroupsMatchFrozenMatrix(string $name): void
    {
        $input = SlugCorpus::cases()[$name]['input'];
        $expected = SlugCorpus::disagreements()[$name] ?? [SlugImplementations::ALL];

        $this->assertSame($expected, $this->liveGroups($input)['groups']);
    }

    public function testMatrixOnlyListsInputsWhereImplementationsActuallyDisagree(): void
    {
        foreach (SlugCorpus::disagreements() as $name => $groups) {
            $this->assertArrayHasKey($name, SlugCorpus::cases());
            $this->assertGreaterThan(1, count($groups), "{$name} listed but all implementations agree");
        }
    }

    public function testMatrixCoversTheKnownDivergentInputs(): void
    {
        $matrix = SlugCorpus::disagreements();

        $this->assertArrayHasKey('dot_inside', $matrix, 'Dr.Who');
        $this->assertArrayHasKey('ampersand', $matrix, 'a&b');
        $this->assertArrayHasKey('empty', $matrix);
        $this->assertArrayHasKey('all_symbols', $matrix);
        $this->assertArrayHasKey('accents', $matrix);
        $this->assertArrayHasKey('cjk', $matrix);
        $this->assertArrayHasKey('rtl_arabic', $matrix);
    }

    public function testFileDiscoveryDivergesFromCategoryDirectoryNamesForDotAndAmpersand(): void
    {
        $this->assertSame('dr-who', SlugImplementations::slug(SlugImplementations::CATEGORIES, 'Dr.Who'));
        $this->assertSame('drwho', SlugImplementations::slug(SlugImplementations::FILE_DISCOVERY, 'Dr.Who'));
        $this->assertSame('a-b', SlugImplementations::slug(SlugImplementations::CATEGORIES, 'a&b'));
        $this->assertSame('ab', SlugImplementations::slug(SlugImplementations::FILE_DISCOVERY, 'a&b'));
    }
}
