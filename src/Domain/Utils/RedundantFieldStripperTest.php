<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Utils;

use PHPUnit\Framework\TestCase;

class RedundantFieldStripperTest extends TestCase
{
    /**
     * Real bug report (2026-08) : a manuscript titre kept verbatim restates data now
     * separately resolved elsewhere (auteur byline, périodique/date/pages of a trailing
     * citation block) -- auteur is stored "Nom, Prénom" (fr.wiki convention) but the
     * title has it in natural "Prénom Nom" order, and the page range sits inside a
     * "{{p.|X}}" template rather than as bare text.
     */
    public function testStripsRedundantFieldsFromARealCitationStyleTitle()
    {
        $titre = 'Alain Collomp, Alliance et filiation en haute Provence au {{s-|XVIII}} (Annales 1977, {{p.|445-477}}))';

        $result = RedundantFieldStripper::strip($titre, [
            'auteur1' => 'Collomp, Alain',
            'périodique' => 'Annales',
            'date' => '1977',
            'pages' => '445-477',
            'volume' => '32',
            'numéro' => '3',
            'doi' => '10.3406/ahess.1977.293833',
            'éditeur' => '[[Persée (portail)|Persée]]',
        ]);

        self::assertStringNotContainsString('Collomp', $result);
        self::assertStringNotContainsString('Alain', $result);
        self::assertStringNotContainsString('Annales', $result);
        self::assertStringNotContainsString('1977', $result);
        self::assertStringNotContainsString('445-477', $result);
        self::assertStringContainsString('Alliance et filiation en haute Provence au {{s-|XVIII}}', $result);
    }

    public function testFlipsNomPrenomAuthorOrderToMatchNaturalTitlePhrasing()
    {
        $result = RedundantFieldStripper::strip(
            'Jean Dupont, Histoire de la Provence',
            ['auteur1' => 'Dupont, Jean']
        );

        self::assertSame('Histoire de la Provence', $result);
    }

    public function testRemovesPagesWrappedInACommonPageTemplateEntirely()
    {
        $result = RedundantFieldStripper::strip(
            'Un article ({{p.|12-34}})',
            ['pages' => '12-34']
        );

        self::assertSame('Un article', $result, 'the whole "{{p.|12-34}}" shell is removed, not left as empty "{{p.|}}" debris');
    }

    public function testMatchesAcrossHyphenAndEnDashVariants()
    {
        $result = RedundantFieldStripper::strip(
            'Titre (p. 12–34)',
            ['pages' => '12-34']
        );

        self::assertStringNotContainsString('12', $result);
        self::assertStringNotContainsString('34', $result);
    }

    /**
     * Real risk this guards against : a bare "32" (numéro/volume) must not eat part of
     * an unrelated 4-digit year like "1932" just because "32" is a substring of it.
     */
    public function testDoesNotCorruptAnUnrelatedNumberSharingADigitSubstring()
    {
        $result = RedundantFieldStripper::strip(
            'Recensement de la population en 1932',
            ['numéro' => '32']
        );

        self::assertSame('Recensement de la population en 1932', $result, 'too short + no word boundary : left untouched');
    }

    public function testRemovesAStandaloneNumberOnACleanWordBoundary()
    {
        // Only the VALUE itself is removed, not a descriptive label word around it
        // ("numéro", "vol.") -- this class strips known field VALUES, not citation
        // prose vocabulary, so the number is chosen with no such label attached here.
        $result = RedundantFieldStripper::strip(
            'Bulletin municipal (125)',
            ['numéro' => '125']
        );

        self::assertSame('Bulletin municipal', $result);
    }

    public function testNeverRemovesAValueShorterThanTheMinimumNeedleLength()
    {
        // "32" alone (2 chars) is below MIN_NEEDLE_LENGTH regardless of word-boundary
        // safety -- too short a value carries too little confidence on its own,
        // deliberately more conservative than the word-boundary guard alone would be.
        $result = RedundantFieldStripper::strip(
            'Bulletin municipal, numéro 32',
            ['numéro' => '32']
        );

        self::assertSame('Bulletin municipal, numéro 32', $result);
    }

    public function testLeavesUnrelatedTextCompletelyUnchanged()
    {
        $titre = 'Un vrai titre original';

        $result = RedundantFieldStripper::strip($titre, [
            'auteur1' => 'Martin, Paul',
            'date' => '2020',
            'périodique' => 'Le Monde',
        ]);

        self::assertSame($titre, $result);
    }

    public function testSkipsARemovalThatWouldLeaveUnbalancedMarkup()
    {
        // "XVIII" also happens to be the known value here, but it's INSIDE a template
        // -- removing it would leave "{{s-|}}" broken open ; RedundantFieldStripper
        // never targets template arguments directly (no template-aware surface form
        // exists for a 'siecle'-like param), so this is really testing that a value
        // occurring only inside markup with no safe removal path is left alone.
        $titre = 'Chose au {{s-|XVIII}} siècle';

        $result = RedundantFieldStripper::strip($titre, ['siecle' => 'XVIII']);

        self::assertSame($titre, $result);
    }

    // --- cleanupSeparators() ---

    public function testCollapsesRepeatedSeparators()
    {
        self::assertSame('a, b', RedundantFieldStripper::cleanupSeparators('a, , b'));
    }

    public function testRemovesEmptyParenthesesLeftBySeparatorsOnly()
    {
        self::assertSame('Titre', RedundantFieldStripper::cleanupSeparators('Titre ( , )'));
        self::assertSame('Titre', RedundantFieldStripper::cleanupSeparators('Titre ()'));
    }

    public function testCollapsesMultipleSpaces()
    {
        self::assertSame('a b', RedundantFieldStripper::cleanupSeparators('a    b'));
    }

    /**
     * French typography wants a space before "?"/"!"/":"/";" -- unlike a stray space
     * before a comma or period, that one must be PRESERVED, not stripped.
     */
    public function testKeepsTheFrenchSpaceBeforeQuestionMark()
    {
        self::assertSame(
            'Les batteries Li-Ion bientôt dépassées ?',
            RedundantFieldStripper::cleanupSeparators('Les batteries Li-Ion bientôt dépassées ?')
        );
    }

    public function testStripsAStraySpaceBeforeACommaOrPeriod()
    {
        self::assertSame('Titre, suite.', RedundantFieldStripper::cleanupSeparators('Titre , suite .'));
    }

    public function testTrimsLeadingAndTrailingSeparatorDebris()
    {
        self::assertSame('Milieu', RedundantFieldStripper::cleanupSeparators(', ; Milieu ,'));
    }
}
