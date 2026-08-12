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
        // the ", ''Têtu''." part is now parsed by ItalicSiteAfterCommaExtractor (Lot 2)
        $this::assertSame('Têtu', $dto->hints['site'] ?? null);
        $this::assertTrue($dto->isFullyConsumed());
    }

    // --- GREEN : ~7.6% of the corpus, the "sur Site" hint extractor (Lot 2) ---

    /**
     * @dataProvider provideSiteMentionFragments
     */
    public function testExtractsSiteMentionAfterSur(string $fragment, string $expectedSite)
    {
        $dto = $this->parser()->parse($fragment);

        $this::assertNotNull($dto);
        $this::assertSame($expectedSite, $dto->hints['site'] ?? null);
        $this::assertTrue($dto->isFullyConsumed());
    }

    public static function provideSiteMentionFragments(): array
    {
        return [
            'bare domain' => [
                '<ref>[http://www.presence-pc.com/actualite/batterie-Li-Ion-36696/ « Les batteries Li-Ion bientôt dépassées ? »], sur presence-pc.com</ref>',
                'presence-pc.com',
            ],
            'bare domain, {{en}} prefix' => [
                '<ref>{{en}} [http://www.infinityward.com/games.php#/videos?id=8 Modern Warfare 2 Launch Trailer], sur infinityward.com</ref>',
                'infinityward.com',
            ],
            'italic site name + trailing period' => [
                "* [http://www.blamont.info/index.html Site Blâmont.info], sur ''Blâmont.info''.",
                'Blâmont.info',
            ],
        ];
    }

    /**
     * Guard against over-matching : a comma-separated trailing mention that does NOT
     * use "sur" (e.g. "..., sallesdecinemas.blogspot.com, 25 octobre 2020.") must NOT be
     * captured as a site hint by SiteMentionExtractor -- that's a different, still-wip
     * pattern (see testExtractsTrailingDate).
     */
    public function testDoesNotMisfireOnCommaWithoutSur()
    {
        $dto = $this->parser()->parse(
            "<ref>[https://sallesdecinemas.blogspot.com/2020/10/alexandra-paris-16eme.html « Alexandra (Paris 16ème) »], sallesdecinemas.blogspot.com, 25 octobre 2020.</ref>"
        );

        $this::assertNotNull($dto);
        $this::assertArrayNotHasKey('site', $dto->hints);
        $this::assertSame(', sallesdecinemas.blogspot.com, 25 octobre 2020.', $dto->rest);
    }

    /**
     * @group wip
     * SiteMentionExtractor deliberately only matches when the whole rest is "sur X" --
     * descriptive phrasings ("sur le site officiel du constructeur", "sur le site de
     * [[X]]") would otherwise be mis-captured as a literal site name ("le site officiel").
     * Target: strip the "le site (de/du/officiel...)" wrapper before treating the
     * remainder as the site value, or route through a wikilink-aware variant.
     */
    public function testDoesNotMisparseDescriptiveSiteMention()
    {
        $dto = $this->parser()->parse(
            '<ref>[http://www.medicen.org/ Site officiel du pôle Medicen.] sur le site officiel</ref>'
        );

        $this::markTestIncomplete('Target: either no "site" hint at all, or a correctly stripped one -- not "le site officiel".');
    }

    // --- GREEN : ~33% of the corpus, the ", ''Site''"/", [[Site]]" hint extractor (Lot 2) ---

    /**
     * @dataProvider provideItalicSiteFragments
     */
    public function testExtractsItalicSiteNameAfterComma(string $fragment, string $expectedSite, ?string $expectedDate)
    {
        $dto = $this->parser()->parse($fragment);

        $this::assertNotNull($dto);
        $this::assertSame($expectedSite, $dto->hints['site'] ?? null);
        // now that TrailingDateExtractor runs right after in the chain (Lot 2 cont'd),
        // the date that used to be left in $rest is consumed too. (isFullyConsumed() is
        // NOT asserted here : one data set below has a wikilinked institution before the
        // bracket, which lands in $leadingText and is untouched by either extractor --
        // see testItalicSiteExtractorDoesNotTouchLeadingText.)
        $this::assertSame($expectedDate, $dto->hints['date'] ?? null);
    }

    public static function provideItalicSiteFragments(): array
    {
        return [
            'italic site, trailing date' => [
                "<ref>[http://www.thewhir.com/web-hosting-news/united-internet-acquires-fasthosts United Internet Acquires FastHosts], ''The Whir'', 10 mai 2006.</ref>",
                'The Whir',
                '10 mai 2006',
            ],
            'italic site, trailing year only' => [
                "<ref>{{en}} [https://www.independent.co.uk/life-style/health-and-families/health-news/drugs-the-real-deal-410086.html « Drugs: the real deal »], ''The Independent'', 2006.</ref>",
                'The Independent',
                '2006',
            ],
            'piped wikilink nested inside italics, no date to find' => [
                "<ref>[[Université Johns-Hopkins]], [https://www.ncbi.nlm.nih.gov/omim/604004 604004], ''[[Héritage mendélien chez l'humain]]''.</ref>",
                "Héritage mendélien chez l'humain",
                null,
            ],
        ];
    }

    /**
     * "., ..." after a wikilinked author/institution before the bracket (here
     * "[[Université Johns-Hopkins]], ") stays in $leadingText, untouched by this
     * extractor which only ever looks at $rest.
     */
    public function testItalicSiteExtractorDoesNotTouchLeadingText()
    {
        $dto = $this->parser()->parse(
            "<ref>[[Université Johns-Hopkins]], [https://www.ncbi.nlm.nih.gov/omim/604004 604004], ''[[Héritage mendélien chez l'humain]]''.</ref>"
        );

        $this::assertNotNull($dto);
        $this::assertSame('[[Université Johns-Hopkins]],', $dto->leadingText);
    }

    // --- GREEN : ~50% of the corpus, the trailing-date hint extractor (Lot 2 cont'd) ---

    /**
     * @dataProvider provideTrailingDateFragments
     */
    public function testExtractsTrailingDate(string $fragment, string $expectedDate)
    {
        $dto = $this->parser()->parse($fragment);

        $this::assertNotNull($dto);
        $this::assertSame($expectedDate, $dto->hints['date'] ?? null);
        // isFullyConsumed() NOT asserted : one data set has an author/institution before
        // the bracket (-> non-empty $leadingText), unrelated to date extraction itself.
    }

    public static function provideTrailingDateFragments(): array
    {
        return [
            'plain day+month+year' => [
                '<ref>[https://www.minorplanetcenter.net/mpec/K18/K18P88.html MPEC 2018-P88 : P/2018 L4 (PANSTARRS) ], 13 août 2018.</ref>',
                '13 août 2018',
            ],
            'bare year' => [
                '<ref>[http://dnf.asso.fr/Zoom-sur-la-e-cigarette-les-points.html?var_recherche=cigarette%20electronique DNF, zoom sur la cigarette électronique], 2013.</ref>',
                '2013',
            ],
            '{{date|...}} single combined param, month+year only' => [
                '<ref>Observatoire sociétal des cancers (2014° [http://www.ligue-cancer.net/sites/default/files/rapport-2013-observatoire-societal-des-cancers.pdf \'\'Rapport 2013\'\'], {{date|avril 2014}}.</ref>',
                'avril 2014',
            ],
            '{{date|...}} single combined param, ordinal day' => [
                '<ref>[https://www.minorplanetcenter.net/mpec/K24/K24E01.html MPEC 2024-E01 : COMET C/2019 G2 (PANSTARRS)], {{date|1er mars 2024}}.</ref>',
                '1er mars 2024',
            ],
            '{{date|...}} with trailing calendar context, discarded' => [
                '<ref>[https://www.minorplanetcenter.net/mpec/K20/K20P10.html MPEC 2020-P10 : COMET C/2020 O2 (Aramal)], {{date|2 août 2020|en astronomie}}.</ref>',
                '2 août 2020',
            ],
        ];
    }

    public function testTrailingDateExtractorHandlesThreePipeSeparatedTemplateParams()
    {
        $dto = $this->parser()->parse(
            "<ref>[http://www.charles-de-gaulle.org/article.php3?id_article=62 Discours du Forum d'Alger], {{date|4|juin|1958}}.</ref>"
        );

        $this::assertNotNull($dto);
        $this::assertSame('4 juin 1958', $dto->hints['date'] ?? null);
        $this::assertTrue($dto->isFullyConsumed());
    }

    /**
     * DateUtil::simpleFrench2object() rejects a calendar-impossible day+month+year
     * (31 February) -- the extractor must not blindly trust the regex shape and store a
     * bogus date hint.
     */
    public function testTrailingDateExtractorRejectsInvalidCalendarDate()
    {
        $dto = $this->parser()->parse(
            '<ref>[http://example.org/x Titre], 31 février 2019.</ref>'
        );

        $this::assertNotNull($dto);
        $this::assertArrayNotHasKey('date', $dto->hints);
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
