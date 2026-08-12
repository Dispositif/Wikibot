<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Tests\ExternLink\Raw;

use App\Domain\ExternLink\Raw\RawExternLinkParser;
use PHPUnit\Framework\TestCase;

/**
 * TDD backlog for the "raw bracketed extern link -> {{lien web}}" feature (see
 * audits/situation-projet-WP-liens-externes.md and the Lot 0 corpus mining script
 * rawExternLinkCorpusScan.php).
 *
 * All fixtures below are REAL fragments sampled from resources/corpus_raw_extern_link.txt
 * (~33k fragments mined from fr.wikipedia.org via CirrusSearch, 2026-08-12), not invented
 * examples — that's the point : they define the patterns the parser actually has to
 * handle, ranked by how often they occur (percentages from that corpus, n=32816).
 *
 * Two groups :
 * - the default group : currently GREEN, RawExternLinkParser handles these today.
 * - "@group wip" : RED on purpose, excluded from the default `vendor/bin/phpunit` run
 *   (see phpunit.xml <groups><exclude>) — each documents one not-yet-implemented
 *   pattern with a real fragment and the target behaviour, so implementing it is
 *   "make this test pass", not "invent a test".
 */
class RawExternLinkParserTest extends TestCase
{
    private function parser(): RawExternLinkParser
    {
        return new RawExternLinkParser();
    }

    // --- GREEN : ~35% of the corpus is this simple (label = whole story, no rest) ---

    /**
     * @dataProvider provideCleanRefFragments
     */
    public function testParsesCleanRefWithNoResidue(string $fragment, string $expectedUrl, string $expectedTitre)
    {
        $dto = $this->parser()->parse($fragment);

        $this::assertNotNull($dto);
        $this::assertSame($expectedUrl, $dto->url);
        $this::assertSame($expectedTitre, $dto->titre);
        $this::assertFalse($dto->isBullet);
        $this::assertTrue($dto->isFullyConsumed());
    }

    public static function provideCleanRefFragments(): array
    {
        return [
            'trailing period' => [
                '<ref>[http://www.sustainx.com SustainX].</ref>',
                'http://www.sustainx.com',
                'SustainX',
            ],
            'no trailing punctuation' => [
                '<ref>[https://catalogue.bnf.fr/ark:/12148/cb16665235p Bibliothèque Nationale de France]</ref>',
                'https://catalogue.bnf.fr/ark:/12148/cb16665235p',
                'Bibliothèque Nationale de France',
            ],
            'title with internal spaces/digits/dashes' => [
                '<ref>[http://memorialdormans.free.fr/CommunesCroixDeGuerre39-45.pdf Communes décorées de la Croix de guerre 1939 - 1945 ].</ref>',
                'http://memorialdormans.free.fr/CommunesCroixDeGuerre39-45.pdf',
                'Communes décorées de la Croix de guerre 1939 - 1945',
            ],
        ];
    }

    /**
     * @dataProvider provideCleanBulletFragments
     */
    public function testParsesCleanBulletWithNoResidue(string $fragment, string $expectedUrl, string $expectedTitre)
    {
        $dto = $this->parser()->parse($fragment);

        $this::assertNotNull($dto);
        $this::assertSame($expectedUrl, $dto->url);
        $this::assertSame($expectedTitre, $dto->titre);
        $this::assertTrue($dto->isBullet);
        $this::assertTrue($dto->isFullyConsumed());
    }

    public static function provideCleanBulletFragments(): array
    {
        return [
            'plain bullet' => [
                '* [http://www.tourisme-tenareze.com/ Office de tourisme]',
                'http://www.tourisme-tenareze.com/',
                'Office de tourisme',
            ],
            'bullet with italic label (site name kept as-is, not yet parsed out)' => [
                "* [http://www.rando-evasion.org/pages/Courcelles_sur_VesleHistoire_et_Patrimoine-8903881.html ''Courcelles sur Vesle Histoire et Patrimoine'']",
                'http://www.rando-evasion.org/pages/Courcelles_sur_VesleHistoire_et_Patrimoine-8903881.html',
                "''Courcelles sur Vesle Histoire et Patrimoine''",
            ],
        ];
    }

    // --- GREEN : <ref name="..."> / <ref group="..."> attributes ---

    public function testCapturesRefName()
    {
        $dto = $this->parser()->parse(
            '<ref name="calais">[http://catalogue.bnf.fr/ark:/12148/cb451967446 Fiche de l\'album et avis du CNLJ, site de la BnF.]</ref>'
        );

        $this::assertNotNull($dto);
        $this::assertSame('calais', $dto->refName);
        $this::assertNull($dto->refGroup);
    }

    public function testCapturesRefGroup()
    {
        $dto = $this->parser()->parse(
            '<ref group="a">{{en}} [http://www.nvr.navy.mil/NVRSERVICECRAFT/DETAILS/YTB836.HTM Page de l\'USS \'\'Pokagon\'\']</ref>'
        );

        $this::assertNotNull($dto);
        $this::assertSame('a', $dto->refGroup);
        $this::assertSame(['en'], $dto->leadingTemplates);
    }

    // --- GREEN : leading {{en}}/{{pdf}} template stripped from the label ---

    /**
     * @dataProvider provideLeadingTemplateFragments
     */
    public function testStripsLeadingTemplate(string $fragment, array $expectedTemplates, string $expectedTitre)
    {
        $dto = $this->parser()->parse($fragment);

        $this::assertNotNull($dto);
        $this::assertSame($expectedTemplates, $dto->leadingTemplates);
        $this::assertSame($expectedTitre, $dto->titre);
    }

    public static function provideLeadingTemplateFragments(): array
    {
        return [
            '{{en}}' => [
                '<ref>{{en}} [http://www.ukbutterflies.co.uk/species.php?species=pandora UK Butterflies].</ref>',
                ['en'],
                'UK Butterflies',
            ],
            '{{PDF}} (case-insensitive)' => [
                '<ref>{{PDF}} [http://www.chubri-galo.bzh/docs/files/z-ortograf/Regles-orthograph-gallo-moga-05-2016.pdf Règles orthographiques pour le gallo].</ref>',
                ['pdf'],
                'Règles orthographiques pour le gallo',
            ],
        ];
    }

    // --- GREEN : French guillemets around the title are stripped, not kept as noise ---

    public function testStripsGuillemetsAroundTitre()
    {
        $dto = $this->parser()->parse(
            '<ref>[http://www.tetu.com/rubrique/infos/infos_detail.php?id_news=9115 « Ségolène Royal entre en campagne et campe sur ses positions conservatrices »], \'\'Têtu\'\'.</ref>'
        );

        $this::assertNotNull($dto);
        $this::assertSame(
            'Ségolène Royal entre en campagne et campe sur ses positions conservatrices',
            $dto->titre
        );
        // the ", ''Têtu''." part is NOT yet parsed into a site/périodique -- see testExtractsItalicSiteNameAfterComma (wip)
        $this::assertSame(", ''Têtu''.", $dto->rest);
    }

    // --- GREEN : parser stays agnostic to the eventual {{lien web}}/{{article}} choice ---

    /**
     * A subset of raw links (press citations with a date, DOI-bearing pages, scientific
     * domains...) should end up as {{article}}, not {{lien web}} — that decision already
     * exists in ExternRefTransformer::chooseTemplateNameByData() (driven by crawled
     * mapData['date']/['doi'] + data_newspapers.json/data_scientific_domain.json domain
     * config) and must be reused as-is by whatever orchestration layer is built on top
     * of this parser, not reimplemented here.
     *
     * This parser has no opinion on it : parsing a fragment that cites a known-newspaper
     * domain (lemonde.fr here, present in data_newspapers.json) succeeds exactly like any
     * other domain, and RawExternLinkDTO carries no template-related field. The actual
     * {{article}} routing can only be verified once page metadata is crawled — that's
     * Lot 4 (RawExternLinkTransformerTest, HTTP-mocked, not yet built) : it must cover
     * (1) newspaper/scientific domain + crawled date present -> {{article}}, (2) same
     * domain but no date -> falls back to {{lien web}} (existing "date obligatoire"
     * rule), (3) same for a bullet-sourced fragment, not just <ref> ones -- the source
     * shape (ref vs bullet) is NOT currently a factor in chooseTemplateNameByData(),
     * which is worth double-checking against real edits given bullets are apparently
     * less often {{article}}-eligible in practice.
     */
    public function testParserStaysAgnosticToTemplateChoice()
    {
        $dto = $this->parser()->parse(
            '<ref>[https://www.lemonde.fr/televisions-radio/article/2015/12/14/le-retour-des-guignols-sur-canal-un-four-non-decapant_4832047_1655027.html Le retour des « Guignols » sur Canal+, un four non décapant].</ref>'
        );

        $this::assertNotNull($dto);
        $this::assertSame(
            'https://www.lemonde.fr/televisions-radio/article/2015/12/14/le-retour-des-guignols-sur-canal-un-four-non-decapant_4832047_1655027.html',
            $dto->url
        );
        $this::assertSame('Le retour des « Guignols » sur Canal+, un four non décapant', $dto->titre);
        $this::assertFalse(property_exists($dto, 'templateName'), 'RawExternLinkDTO must not encode a template choice.');
    }

    // --- GREEN : a bare URL (no brackets) is out of RawExternLinkParser's scope ---

    public function testReturnsNullForBareUrlWithoutBrackets()
    {
        // ExternRefTransformer already handles this case directly (CheckURL::isURLAuthorized),
        // RawExternLinkParser only exists for the bracketed "[url Libellé]" variant.
        $this::assertNull($this->parser()->parse('<ref>https://x.fr/a</ref>'));
    }

    public function testReturnsNullWhenWrapperIsNotRefOrBullet()
    {
        $this::assertNull($this->parser()->parse("some random text [http://x.fr Titre] more text"));
    }

    // =====================================================================================
    // BACKLOG (@group wip) : RED on purpose. Each documents one unimplemented pattern with
    // a real corpus fragment + the target behaviour. Excluded from the default test run
    // (phpunit.xml). Run with: vendor/bin/phpunit --group wip
    // =====================================================================================

    /**
     * @group wip
     * ~7.6% of the corpus : "[url Titre], sur Site". Target: $dto should expose a
     * predicted 'site' hint ("presence-pc.com") separate from $rest, via a future
     * HintExtractor (see plan §5, RawLinkHints). Today the whole ", sur ..." stays in
     * $rest, unparsed.
     */
    public function testExtractsSiteMentionAfterSur()
    {
        $dto = $this->parser()->parse(
            '<ref>[http://www.presence-pc.com/actualite/batterie-Li-Ion-36696/ « Les batteries Li-Ion bientôt dépassées ? »], sur presence-pc.com</ref>'
        );

        $this::markTestIncomplete('Target: predicted site = "presence-pc.com", $dto->rest empty.');
        // $this::assertSame('presence-pc.com', $dto->hints['site']);
        // $this::assertTrue($dto->isFullyConsumed());
    }

    /**
     * @group wip
     * ~33% of the corpus carries italic markup, frequently the site/périodique name
     * right after the closing bracket : "[url Titre], ''Le Monde'', 12 mars 2019".
     * Target: 'site' or 'périodique' hint extracted from the italic span.
     */
    public function testExtractsItalicSiteNameAfterComma()
    {
        $dto = $this->parser()->parse(
            "<ref>[http://www.thewhir.com/web-hosting-news/united-internet-acquires-fasthosts United Internet Acquires FastHosts], ''The Whir'', 10 mai 2006.</ref>"
        );

        $this::markTestIncomplete('Target: predicted site/périodique = "The Whir", trailing date extracted separately (see testExtractsTrailingDate).');
        // $this::assertSame('The Whir', $dto->hints['site'] ?? $dto->hints['périodique']);
    }

    /**
     * @group wip
     * ~50% of the corpus has a 4-digit year somewhere, ~31% a French month name.
     * Target: a 'date' hint parsed via DateUtil::simpleFrench2object() or similar,
     * whether it trails a comma or stands alone.
     */
    public function testExtractsTrailingDate()
    {
        $dto = $this->parser()->parse(
            '<ref>[https://www.minorplanetcenter.net/mpec/K18/K18P88.html MPEC 2018-P88 : P/2018 L4 (PANSTARRS) ], 13 août 2018.</ref>'
        );

        $this::markTestIncomplete('Target: predicted date = 2018-08-13 (DateUtil), $dto->rest empty.');
        // $this::assertNotNull($dto->hints['date']);
    }

    /**
     * @group wip
     * ~9.6% of the corpus, and ~2.1% without a comma before it (as here). Target:
     * 'consulté le' hint, distinct from the citation's own 'date'.
     */
    public function testExtractsConsulteLeWithoutComma()
    {
        $dto = $this->parser()->parse(
            '<ref>[http://www.saint-maur.com/ Site de la ville] Consulté le 25 juin 2009</ref>'
        );

        $this::markTestIncomplete('Target: predicted "consulté le" = 2009-06-25, $dto->rest empty.');
        // $this::assertNotNull($dto->hints['consulté le']);
    }

    /**
     * @group wip
     * Author name(s) before the bracket, not just after — currently dumped whole into
     * $leadingText. Target: 'auteur' hint (and 'lien auteur' if wikilinked).
     */
    public function testExtractsAuthorPrefix()
    {
        $dto = $this->parser()->parse(
            '<ref>Louis Laroque, [http://www.lepoint.fr/tendances/2008-08-14/maisons-de-stars/998/0/266963 "Maisons de stars"], \'\'Le Point\'\', 14 août 2008</ref>'
        );

        $this::markTestIncomplete('Target: predicted auteur = "Louis Laroque", périodique = "Le Point", date = 2008-08-14.');
        // $this::assertSame('Louis Laroque', $dto->hints['auteur']);
    }

    /**
     * @group wip
     * Book-like citations (ISBN, "p. NN", multiple bracketed links in one <ref>) are
     * probably out of scope for {{lien web}} entirely and should route to the
     * {{ouvrage}} pipeline instead (or be skipped) rather than being force-fit here.
     * Target: parser (or the transformer built on top of it) should flag/skip these,
     * not attempt a {{lien web}} merge.
     */
    public function testFlagsBookLikeCitationAsOutOfScope()
    {
        $dto = $this->parser()->parse(
            "<ref>Nicolas Appert, [https://books.google.fr/books?id=AGcOAAAAQAAJ&pg=PA33&lpg=PA33&dq=result# ''Livre de tous les ménages ou l'art de conserver pendant plusieurs années''], Barrois l'aîné, 1831, {{p.}}33</ref>"
        );

        $this::markTestIncomplete('Target: some out-of-scope signal (e.g. isBookLike()) so the caller skips/reroutes instead of emitting a lossy {{lien web}}.');
    }
}
