<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Tests\ExternLink\Raw;

use App\Domain\ExternLink\Raw\ResidueReducer;
use PHPUnit\Framework\TestCase;

/**
 * All fixtures below are real {{lien web}}/{{article}} outputs observed during live
 * raw-extern-ref runs (2026-08-13), where the leftover manuscript text was being kept
 * as a 'citation' that merely restated data already present in the template.
 */
class ResidueReducerTest extends TestCase
{
    private function reducer(): ResidueReducer
    {
        return new ResidueReducer();
    }

    /**
     * @dataProvider provideFullyRedundantResidues
     */
    public function testDropsResidueEntirelyExplainedByTemplateData(
        string $residue,
        array $mapData,
        ?string $url
    ) {
        self::assertNull($this->reducer()->reduce($residue, $mapData, $url));
    }

    public static function provideFullyRedundantResidues(): array
    {
        return [
            'site name spelled out matches the concatenated domain' => [
                'Mpumalanga Happenings',
                ['titre' => 'The history of Witbank', 'site' => 'mpumalangahappenings.co.za'],
                'http://www.mpumalangahappenings.co.za/witbank_homepage.htm',
            ],
            'site + date, manuscript "1er novembre 2018" vs crawled "01-11-2018"' => [
                'Witbank News, 1er novembre 2018',
                ['titre' => 'We have the dirtiest air in the world', 'site' => 'Witbank News', 'date' => '01-11-2018'],
                'https://witbanknews.co.za/116572/dirtiest-air-world/',
            ],
            'périodique behind a wikilink, plus a relational stop word' => [
                'pour Le Figaro',
                ['titre' => 'Les magistrats appellent à reprendre les audiences', 'périodique' => '[[Le Figaro]]'],
                null,
            ],
            'author restated in reverse word order' => [
                "d'Henri Lefebvre",
                ['titre' => 'LEFEBVRE Henri, François', 'site' => 'maitron.fr'],
                'https://maitron.fr/spip.php?article107790',
            ],
            'punctuation only' => ['-- , .', ['titre' => 'Bla'], null],
        ];
    }

    /**
     * The user-facing goal : "Biographie Le Maitron d'Henri Lefebvre" should keep only
     * what isn't already in the template -- "Le Maitron" is the domain (maitron.fr),
     * "Henri Lefebvre" is the title reworded, "d'" is a stop word.
     */
    public function testKeepsOnlyTheGenuinelyNewWords()
    {
        $reduced = $this->reducer()->reduce(
            'Biographie Le Maitron d\'Henri Lefebvre"',
            ['titre' => 'LEFEBVRE Henri, François', 'site' => 'maitron.fr'],
            'https://maitron.fr/spip.php?article107790'
        );

        self::assertSame('Biographie', $reduced);
    }

    public function testKeepsGenuinelyNewContentUntouched()
    {
        $residue = "Vidéo présentant au ralenti le décollage d'une libellule";
        $reduced = $this->reducer()->reduce(
            $residue,
            ['titre' => 'Dragonfly action in slow motion', 'site' => '[[YouTube]]'],
            'https://www.youtube.com/watch?v=HdKxmvcRxls'
        );

        self::assertSame($residue, $reduced);
    }

    /**
     * Reconstruction never removes words from the MIDDLE : only the redundant head and
     * tail are trimmed, so prose between two new words stays readable verbatim rather
     * than being reassembled into word soup.
     */
    public function testPreservesTheTextBetweenTheFirstAndLastNewWord()
    {
        $reduced = $this->reducer()->reduce(
            'Le Monde : dossier complet sur Le Monde',
            ['titre' => 'Bla', 'site' => 'Le Monde'],
            null
        );

        self::assertSame('dossier complet', $reduced);
    }

    /**
     * A hyphenated compound is only dropped when EVERY part is itself redundant, so a
     * name half-matching a known value survives whole instead of being cut in two.
     */
    public function testKeepsCompoundWordWhenOnlyOneHalfIsKnown()
    {
        $reduced = $this->reducer()->reduce(
            'Jean-Rostand',
            ['titre' => 'Jean', 'site' => 'example.org'],
            null
        );

        self::assertSame('Jean-Rostand', $reduced);
    }

    /**
     * The span ends at the last surviving WORD, which can sit inside wiki markup and cut
     * it open ("{{p.|173-209" without its closing braces) -- corrupting the rendered
     * page. When trimming would unbalance the markup, the residue is kept whole instead :
     * valid wikitext outranks tidiness.
     *
     * @dataProvider provideResiduesWhoseTrimmingWouldBreakMarkup
     */
    public function testKeepsResidueWholeRatherThanCuttingMarkupOpen(string $residue, array $mapData)
    {
        self::assertSame($residue, $this->reducer()->reduce($residue, $mapData, null));
    }

    public static function provideResiduesWhoseTrimmingWouldBreakMarkup(): array
    {
        return [
            'trailing }} would be cut off' => [
                '1964, tome 52 {{numéro|2}}. {{p.|173-209}}',
                ['titre' => 'Bla', 'site' => 'persee.fr'],
            ],
            'leading [[ would be cut off' => [
                'Le Monde, cf. [[Article X]] et suite',
                ['titre' => 'Bla', 'site' => 'Le Monde'],
            ],
        ];
    }

    public function testMatchesDateWrittenAsPlainTextAgainstIsoCrawledDate()
    {
        self::assertNull(
            $this->reducer()->reduce('12 mars 2019', ['date' => '2019-03-12'], null)
        );
    }

    /**
     * Guard against over-reach : a month name that is NOT the template's own month must
     * survive, otherwise any date-looking text would be silently swallowed.
     */
    public function testKeepsADateThatDisagreesWithTheTemplateDate()
    {
        $reduced = $this->reducer()->reduce('12 septembre 2019', ['date' => '2019-03-12'], null);

        self::assertSame('septembre', $reduced);
    }
}
