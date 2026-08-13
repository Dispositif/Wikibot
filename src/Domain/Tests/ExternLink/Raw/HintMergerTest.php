<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Tests\ExternLink\Raw;

use App\Domain\ExternLink\Raw\HintMerger;
use App\Domain\ExternLink\Raw\MergeConfidence;
use App\Domain\ExternLink\Raw\RawExternLinkDTO;
use App\Domain\ExternLink\Raw\RawExternLinkParser;
use PHPUnit\Framework\TestCase;

/**
 * Lot 3 : HintMerger arbitrates between a RawExternLinkParser result (manuscript hints,
 * Lot 1-2) and crawled page metadata (ExternMapper::process()'s output shape). Pure
 * domain logic, no I/O -- crawled data is a fixture array here, not a real HTTP fetch
 * (that's Lot 4, RawExternLinkTransformerTest, HTTP-mocked). See HintMerger's own
 * docblock for the full arbitration table.
 */
class HintMergerTest extends TestCase
{
    private function merger(): HintMerger
    {
        return new HintMerger();
    }

    private function parse(string $fragment): RawExternLinkDTO
    {
        $dto = (new RawExternLinkParser())->parse($fragment);
        self::assertNotNull($dto, 'fixture fragment must parse');

        return $dto;
    }

    // --- site : gap-filling, agreement, conflict ---

    public function testFillsMissingSiteFromManuscript()
    {
        $raw = $this->parse(
            '<ref>[http://www.presence-pc.com/actualite/batterie-Li-Ion-36696/ « Les batteries Li-Ion bientôt dépassées ? »], sur presence-pc.com</ref>'
        );

        $result = $this->merger()->merge($raw, ['titre' => 'Les batteries Li-Ion bientôt dépassées ?']);

        self::assertSame('presence-pc.com', $result->mapData['site']);
        self::assertSame(MergeConfidence::Auto, $result->confidence);
        self::assertSame([], $result->conflicts);
    }

    public function testCrawledSiteWinsAndNoConflictWhenSimilarToManuscript()
    {
        $raw = $this->parse(
            '<ref>[http://www.presence-pc.com/actualite/batterie-Li-Ion-36696/ « Les batteries Li-Ion bientôt dépassées ? »], sur presence-pc.com</ref>'
        );

        $result = $this->merger()->merge($raw, ['titre' => 'Bla', 'site' => 'presence-pc.com']);

        self::assertSame('presence-pc.com', $result->mapData['site']);
        self::assertArrayNotHasKey('site', $result->conflicts);
        self::assertSame(MergeConfidence::Auto, $result->confidence);
    }

    public function testFlagsConflictAndKeepsCrawledSiteWhenTheyDisagree()
    {
        $raw = $this->parse(
            '<ref>[http://www.presence-pc.com/actualite/batterie-Li-Ion-36696/ « Les batteries Li-Ion bientôt dépassées ? »], sur presence-pc.com</ref>'
        );

        $result = $this->merger()->merge($raw, ['titre' => 'Bla', 'site' => 'Le Figaro']);

        self::assertSame('Le Figaro', $result->mapData['site'], 'crawled site kept, not silently overwritten');
        self::assertSame(
            ['manuscript' => 'presence-pc.com', 'crawled' => 'Le Figaro'],
            $result->conflicts['site']
        );
        self::assertSame(MergeConfidence::SemiAuto, $result->confidence);
    }

    // --- titre : EDITORIAL field, manuscript wins by default (2026-08 revision) ---

    public function testManuscriptTitleWinsByDefaultEvenOverAPerfectlyFineCrawledTitle()
    {
        $raw = $this->parse(
            '<ref>[http://www.tourisme-tenareze.com/ Office de tourisme]</ref>'
        );

        $result = $this->merger()->merge($raw, ['titre' => 'Office de Tourisme de Ténarèze - Accueil visiteurs', 'site' => 'tourisme-tenareze.com']);

        self::assertSame('Office de tourisme', $result->mapData['titre'], 'the human-written label is kept, not the crawled title, even though nothing is wrong with the crawled one');
    }

    public function testUsesManuscriptTitleWhenCrawlHasNone()
    {
        $raw = $this->parse(
            '<ref>[http://www.tourisme-tenareze.com/ Office de tourisme]</ref>'
        );

        $result = $this->merger()->merge($raw, []);

        self::assertSame('Office de tourisme', $result->mapData['titre']);
    }

    /**
     * @dataProvider provideGenericManuscriptTitleFragments
     */
    public function testFallsBackToCrawledTitleWhenManuscriptLabelIsAPlaceholder(string $fragment)
    {
        $raw = $this->parse($fragment);

        $result = $this->merger()->merge($raw, ['titre' => 'Harvey Weinstein’s Army of Spies']);

        self::assertSame('Harvey Weinstein’s Army of Spies', $result->mapData['titre']);
    }

    public static function provideGenericManuscriptTitleFragments(): array
    {
        return [
            '"en ligne" placeholder label' => [
                '<ref>Ronan Farrow, « Harvey Weinstein’s Army of Spies », [https://www.newyorker.com/news/news-desk/harvey-weinsteins-army-of-spies en ligne].</ref>',
            ],
            '"lire en ligne" placeholder label' => [
                "<ref>Amédée le Boucq de Ternas, ''Recueil de la noblesse des Pays-Bas, de Flandre et d'Artois'', Douai, 1884, p. 286, [https://gallica.bnf.fr/ark:/12148/bpt6k3742772?rk=21459;2 lire en ligne].</ref>",
            ],
        ];
    }

    public function testKeepsGenericManuscriptLabelWhenNoCrawledAlternative()
    {
        $raw = $this->parse(
            '<ref>[https://www.newyorker.com/news/news-desk/harvey-weinsteins-army-of-spies en ligne].</ref>'
        );

        $result = $this->merger()->merge($raw, []);

        self::assertSame('en ligne', $result->mapData['titre'], 'a placeholder is still better than no titre at all');
    }

    // --- auteur/date/périodique : EDITORIAL fields, manuscript wins by default ---

    public function testFillsMissingAuteurAndDateFromManuscript()
    {
        $raw = $this->parse(
            '<ref>Louis Laroque, [http://www.lepoint.fr/tendances/2008-08-14/maisons-de-stars/998/0/266963 "Maisons de stars"], \'\'Le Point\'\', 14 août 2008</ref>'
        );

        $result = $this->merger()->merge($raw, ['titre' => 'Maisons de stars']);

        self::assertSame('Louis Laroque', $result->mapData['auteur1']);
        self::assertSame('14 août 2008', $result->mapData['date']);
        self::assertSame('Le Point', $result->mapData['périodique']);
    }

    public function testManuscriptDateOverridesAnExistingCrawledDate()
    {
        $raw = $this->parse(
            '<ref>[https://www.minorplanetcenter.net/mpec/K18/K18P88.html MPEC 2018-P88 : P/2018 L4 (PANSTARRS) ], 13 août 2018.</ref>'
        );

        $result = $this->merger()->merge($raw, ['titre' => 'Bla', 'date' => '2018-08-14']);

        self::assertSame('13 août 2018', $result->mapData['date'], 'the citation date the human wrote is kept, not the crawled one');
    }

    // --- langue : editorial signal, manuscript always wins ---

    public function testManuscriptLangueAlwaysWinsIncludingFr()
    {
        $raw = $this->parse(
            '<ref name="2013_www.lfsm.net">{{fr}} [http://www.lfsm.net/8/mia.htm lfsm.net].</ref>'
        );

        $result = $this->merger()->merge($raw, ['titre' => 'lfsm.net']);

        self::assertSame('fr', $result->mapData['langue']);
    }

    // --- consulté le : TECHNICAL field (a link-check timestamp), crawled wins if present ---

    public function testManuscriptConsulteLeFillsGapWhenCrawlHasNoValue()
    {
        // No crawled source produces 'consulté le' today -- this is the only reachable
        // case in practice right now, see testCrawledConsulteLeWinsOverManuscript below
        // for the (currently unreachable, future-proofed) technical-priority rule.
        $raw = $this->parse(
            '<ref>[http://www.saint-maur.com/ Site de la ville] Consulté le 25 juin 2009</ref>'
        );

        $result = $this->merger()->merge($raw, ['titre' => 'Bla']);

        self::assertSame('25 juin 2009', $result->mapData['consulté le']);
    }

    public function testCrawledConsulteLeWinsOverManuscriptWhenPresent()
    {
        $raw = $this->parse(
            '<ref>[http://www.saint-maur.com/ Site de la ville] Consulté le 25 juin 2009</ref>'
        );

        $result = $this->merger()->merge($raw, ['titre' => 'Bla', 'consulté le' => '12 mars 2026']);

        self::assertSame('12 mars 2026', $result->mapData['consulté le'], "a fresh technical check date beats an old citation's stated access date");
    }

    // --- confidence gating ---

    /**
     * "sur le site de X" is treated as equivalent to "sur X" (2026-08 revision) : the
     * wrapper is stripped at parse time (SiteMentionExtractorTest... no, tested at the
     * RawExternLinkParser level, see RawExternLinkParserTest), and once reduced to a
     * plain 'site' hint it goes through the exact same gap-fill/compare/conflict rule
     * as any other "sur X" mention -- no HintMerger-specific handling needed. Here the
     * crawled site genuinely disagrees ("SIG Sauer" the company name vs. the crawled
     * domain), so it's STILL a legitimate conflict -- SemiAuto is the right call, but
     * for an honest name mismatch now, not a mis-parsed "le site de" wrapper.
     */
    public function testSiteOfMentionStillConflictsWhenItGenuinelyDisagreesWithCrawledSite()
    {
        $raw = $this->parse(
            '<ref>[http://www.sigsauerguns.com/sig-p238-copperhead-ns-380.html P238 Copperhead] sur le site de SIG Sauer</ref>'
        );

        self::assertSame('SIG Sauer', $raw->hints['site'] ?? null, 'the "le site de" wrapper must be stripped');

        $result = $this->merger()->merge($raw, ['titre' => 'P238 Copperhead', 'site' => 'sigsauerguns.com']);

        self::assertSame(MergeConfidence::SemiAuto, $result->confidence);
        self::assertSame(
            ['manuscript' => 'SIG Sauer', 'crawled' => 'sigsauerguns.com'],
            $result->conflicts['site']
        );
    }

    /**
     * "sur le site officiel" is an editorial judgment ("this URL IS the subject's own
     * homepage"), not a literal site name -- overrides the crawled value outright
     * instead of being compared/conflicting with it (2026-08 revision, fixes a false
     * conflict found in live testing : "le site officiel" used to be captured verbatim
     * and false-conflict with the real crawled domain).
     */
    public function testOfficialSiteMentionOverridesCrawledSite()
    {
        $raw = $this->parse(
            '<ref>[http://www.medicen.org/ Site officiel du pôle Medicen.] sur le site officiel</ref>'
        );

        $result = $this->merger()->merge($raw, ['titre' => 'Site officiel du pôle Medicen.', 'site' => 'medicen.org']);

        self::assertSame('site officiel', $result->mapData['site']);
        self::assertSame([], $result->conflicts);
        self::assertSame(MergeConfidence::Auto, $result->confidence);
    }

    /**
     * Regression (found in live testing, 2026-08-12) : a "; " separator (not "," --
     * none of the Hints/ extractors recognize it) left "Vidéo présentant au ralenti le
     * décollage d'une libellule, sur You Tube par Valvids" entirely unconsumed. Before
     * preserveUnconsumedResidue() existed, confirming the SemiAuto diff silently
     * dropped that whole description + the author attribution ("Valvids") -- the
     * confidence flag correctly asked for a human look, but nothing preserved the data
     * itself if that human said yes without carefully re-reading the raw diff.
     */
    public function testPreservesUnconsumedResidueAsCitationInsteadOfDroppingIt()
    {
        $raw = $this->parse(
            "* [https://www.youtube.com/watch?v=HdKxmvcRxls Dragonfly action in slow motion ] ; Vidéo présentant au ralenti le décollage d'une libellule, sur You Tube par Valvids"
        );

        $result = $this->merger()->merge($raw, ['titre' => 'Dragonfly action in slow motion', 'site' => '[[YouTube]]']);

        self::assertSame(MergeConfidence::SemiAuto, $result->confidence);
        self::assertSame(
            "Vidéo présentant au ralenti le décollage d'une libellule, sur You Tube par Valvids",
            $result->mapData['citation']
        );
    }

    /**
     * A residue ResidueReducer can fully explain away (here : the site name and the
     * date, restated in prose after the bracket) produces NO citation at all, and --
     * since nothing is left unaccounted for -- also lifts confidence back to Auto,
     * exactly as if the extractor chain had consumed it. Real raw-extern-ref output,
     * 2026-08-13 : it used to emit "|citation=Witbank News, 1er novembre 2018".
     */
    public function testFullyRedundantResidueYieldsNoCitationAndStaysAuto()
    {
        $raw = $this->parse(
            '<ref>{{en}} [https://witbanknews.co.za/116572/dirtiest-air-world/ We have the dirtiest air in the world], Witbank News, 1er novembre 2018</ref>'
        );

        $result = $this->merger()->merge(
            $raw,
            ['titre' => 'We have the dirtiest air in the world', 'site' => 'Witbank News', 'date' => '01-11-2018']
        );

        self::assertArrayNotHasKey('citation', $result->mapData);
        self::assertSame(MergeConfidence::Auto, $result->confidence);
    }

    public function testDoesNotOverwriteAnAlreadyPresentCrawledCitation()
    {
        $raw = $this->parse(
            '<ref>[http://www.medicen.org/ Site officiel du pôle Medicen.] sur le site officiel</ref>'
        );

        $result = $this->merger()->merge(
            $raw,
            ['titre' => 'Site officiel du pôle Medicen.', 'citation' => 'Extrait crawlé déjà présent']
        );

        self::assertSame('Extrait crawlé déjà présent', $result->mapData['citation']);
    }

    /**
     * End-to-end (domain-only) sanity check : a real corpus fragment, parsed for real,
     * merged with a plausible crawl fixture, ends up Auto with every field filled --
     * ties Lot 1/2's parser output into Lot 3's merge in one pass.
     */
    public function testFullyConsumedManuscriptWithAgreeingCrawlIsAuto()
    {
        $raw = $this->parse(
            "<ref>Simon Hooper, [https://edition.cnn.com/2005/TECH/science/01/04/norte.chico/ ''New insight into ancient Americans''], [[Cable News Network|CNN]], 4 janvier 2005.</ref>"
        );

        $result = $this->merger()->merge($raw, ['titre' => 'New insight into ancient Americans', 'site' => 'CNN']);

        self::assertSame(MergeConfidence::Auto, $result->confidence);
        self::assertSame('Simon Hooper', $result->mapData['auteur1']);
        self::assertSame('4 janvier 2005', $result->mapData['date']);
        self::assertSame('CNN', $result->mapData['site']);
    }
}
