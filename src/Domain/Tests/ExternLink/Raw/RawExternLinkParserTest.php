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
     * "sur le site officiel [de X]" is recognized as the literal OFFICIAL_SITE_LABEL
     * marker (2026-08 revision) rather than mis-captured as a site name -- the
     * merge-time override (HintMerger lets this win over the crawled site outright) is
     * tested in HintMergerTest::testOfficialSiteMentionOverridesCrawledSite.
     */
    public function testRecognizesOfficialSiteMentionAsALiteralMarker()
    {
        $dto = $this->parser()->parse(
            '<ref>[http://www.medicen.org/ Site officiel du pôle Medicen.] sur le site officiel</ref>'
        );

        $this::assertNotNull($dto);
        $this::assertSame('site officiel', $dto->hints['site'] ?? null);
        $this::assertTrue($dto->isFullyConsumed());
    }

    /**
     * "sur le site de X" (no "officiel") is equivalent to "sur X" : the "le site
     * (du/des/de)" wrapper is stripped, X becomes the hint value and goes through the
     * exact same downstream handling (gap-fill/compare/conflict) as a bare "sur X"
     * mention -- X itself isn't assumed correct, see
     * HintMergerTest::testSiteOfMentionStillConflictsWhenItGenuinelyDisagreesWithCrawledSite
     * for a case where it's wrong and correctly flagged.
     *
     * @dataProvider provideSiteOfFragments
     */
    public function testStripsSiteDeWrapperBeforeTreatingRemainderAsSiteName(string $fragment, string $expectedSite)
    {
        $dto = $this->parser()->parse($fragment);

        $this::assertNotNull($dto);
        $this::assertSame($expectedSite, $dto->hints['site'] ?? null);
    }

    public static function provideSiteOfFragments(): array
    {
        return [
            '"de" + bare name' => [
                '<ref>[http://www.sigsauerguns.com/sig-p238-copperhead-ns-380.html P238 Copperhead] sur le site de SIG Sauer</ref>',
                'SIG Sauer',
            ],
            '"des" contraction' => [
                '<ref group="PUL">[https://presses.univ-lyon2.fr/ Catalogue], sur le site des PUL.</ref>',
                'PUL',
            ],
            '"de la" article kept attached, plus wikilink' => [
                '<ref>[http://data.bnf.fr/13918026/gioachino_rossini_guillaume_tell/ Fiche] sur le site de la [[Bibliothèque nationale de France]].</ref>',
                'la [[Bibliothèque nationale de France]]',
            ],
        ];
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
        // the date that used to be left in $rest is consumed too.
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
     * "[[Université Johns-Hopkins]], ") is untouched by this extractor, which only ever
     * looks at $rest -- it's picked up separately, by AuthorPrefixExtractor's own chain
     * on $leadingText, see testAuthorPrefixExtractorHandlesWikilinkedAuthor.
     */
    public function testItalicSiteExtractorDoesNotTouchLeadingText()
    {
        $dto = $this->parser()->parse(
            "<ref>[[Université Johns-Hopkins]], [https://www.ncbi.nlm.nih.gov/omim/604004 604004], ''[[Héritage mendélien chez l'humain]]''.</ref>"
        );

        $this::assertNotNull($dto);
        $this::assertSame('Héritage mendélien chez l\'humain', $dto->hints['site'] ?? null);
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

    // --- GREEN : ~9.6% of the corpus, the "consulté le" hint extractor (Lot 2 cont'd) ---

    /**
     * @dataProvider provideConsulteLeFragments
     */
    public function testExtractsConsulteLeWithoutComma(string $fragment, string $expectedConsulteLe)
    {
        $dto = $this->parser()->parse($fragment);

        $this::assertNotNull($dto);
        $this::assertSame($expectedConsulteLe, $dto->hints['consulté le'] ?? null);
        // isFullyConsumed() NOT asserted here : one data set has an unrecognized leading
        // language template ({{bg}}, Bulgarian -- not in PREFIX_TEMPLATE_PATTERN), which
        // is unrelated to "consulté le" extraction itself.
    }

    public static function provideConsulteLeFragments(): array
    {
        return [
            'textual date, no comma, no parens' => [
                '<ref>[http://www.saint-maur.com/ Site de la ville] Consulté le 25 juin 2009</ref>',
                '25 juin 2009',
            ],
            'numeric JJ/MM/AAAA, dominant real-world shape' => [
                '<ref>[https://www.linternaute.com/ville/ville/elections-municipales/27468/puget-sur-argens.shtml Liste des élus au conseil municipal en 2008 sur le site linternaute.com] Consulté le 06/07/2009.</ref>',
                '6 juillet 2009',
            ],
            'parenthesized textual date' => [
                "<ref>{{bg}} [http://paisiionline.hit.bg/ Site du lycée Otec Paisij] (consulté le 23 janvier 2009).</ref>",
                '23 janvier 2009',
            ],
        ];
    }

    /**
     * "[url Titre], 30 juin 2011 (consulté le 6 avril 2020)." -- the citation's own date
     * (handled by TrailingDateExtractor) and the access date are two distinct hints,
     * extracted by two different extractors running in sequence on the same $rest.
     */
    public function testConsulteLeExtractorRunsAfterTrailingDateOnTheSameRest()
    {
        $dto = $this->parser()->parse(
            "<ref>[https://actu.fr/normandie/mortagne-au-perche_61293/mamers-il-etait-une-fois-une-gare_5696533.html « Mamers. Il était une fois une gare… »], ''actu.fr'', 30 juin 2011 (consulté le 6 avril 2020).</ref>"
        );

        $this::assertNotNull($dto);
        $this::assertSame('actu.fr', $dto->hints['site'] ?? null);
        $this::assertSame('30 juin 2011', $dto->hints['date'] ?? null);
        $this::assertSame('6 avril 2020', $dto->hints['consulté le'] ?? null);
        $this::assertTrue($dto->isFullyConsumed());
    }

    // --- GREEN : ~2.4% of the corpus, the author/institution prefix extractor (Lot 2 cont'd) ---

    /**
     * @dataProvider provideAuthorPrefixFragments
     */
    public function testExtractsAuthorPrefix(string $fragment, string $expectedAuteur)
    {
        $dto = $this->parser()->parse($fragment);

        $this::assertNotNull($dto);
        $this::assertSame($expectedAuteur, $dto->hints['auteur'] ?? null);
        $this::assertTrue($dto->isFullyConsumed());
    }

    public static function provideAuthorPrefixFragments(): array
    {
        return [
            'two-word author, site+date also extracted from rest' => [
                '<ref>Louis Laroque, [http://www.lepoint.fr/tendances/2008-08-14/maisons-de-stars/998/0/266963 "Maisons de stars"], \'\'Le Point\'\', 14 août 2008</ref>',
                'Louis Laroque',
            ],
            'author + wikilinked site in rest' => [
                "<ref>Simon Hooper, [https://edition.cnn.com/2005/TECH/science/01/04/norte.chico/ ''New insight into ancient Americans''], [[Cable News Network|CNN]], 4 janvier 2005.</ref>",
                'Simon Hooper',
            ],
            'short 2-letter-surname author' => [
                "<ref>Wen Mu, [http://french.peopledaily.com.cn/Horizon/6623053.html Commentaire sur les « Sept questions sur le Tibet » de Elizabeth Gleick], ''Le Quotidien du Peuple en ligne'', 26 juin 2009.</ref>",
                'Wen Mu',
            ],
        ];
    }

    public function testAuthorPrefixAlsoExtractsSiteAndDateFromRest()
    {
        $dto = $this->parser()->parse(
            '<ref>Louis Laroque, [http://www.lepoint.fr/tendances/2008-08-14/maisons-de-stars/998/0/266963 "Maisons de stars"], \'\'Le Point\'\', 14 août 2008</ref>'
        );

        $this::assertNotNull($dto);
        $this::assertSame('Le Point', $dto->hints['site'] ?? null);
        $this::assertSame('14 août 2008', $dto->hints['date'] ?? null);
    }

    /**
     * A wikilinked institution before the bracket ("[[Université Johns-Hopkins]],") is
     * kept verbatim as the 'auteur' hint, wikilink markup and all -- see
     * AuthorPrefixExtractor's docblock for why that's the *correct* value here, not an
     * intermediate form to dereference to plain text.
     */
    public function testAuthorPrefixExtractorHandlesWikilinkedAuthor()
    {
        $dto = $this->parser()->parse(
            "<ref>[[Université Johns-Hopkins]], [https://www.ncbi.nlm.nih.gov/omim/604004 604004], ''[[Héritage mendélien chez l'humain]]''.</ref>"
        );

        $this::assertNotNull($dto);
        $this::assertSame('[[Université Johns-Hopkins]]', $dto->hints['auteur'] ?? null);
        $this::assertSame('', $dto->leadingText);
        $this::assertTrue($dto->isFullyConsumed());
    }

    // --- GREEN : leading {{lang}} templates -> 'langue' hint (Lot 2 cont'd) ---

    /**
     * @dataProvider provideLanguageTemplateFragments
     */
    public function testExtractsLanguageHintFromLeadingTemplate(string $fragment, ?string $expectedLangue)
    {
        $dto = $this->parser()->parse($fragment);

        $this::assertNotNull($dto);
        $this::assertSame($expectedLangue, $dto->hints['langue'] ?? null);
    }

    public static function provideLanguageTemplateFragments(): array
    {
        return [
            '{{en}} -> langue=en' => [
                '<ref>{{en}} [http://www.ukbutterflies.co.uk/species.php?species=pandora UK Butterflies].</ref>',
                'en',
            ],
            '{{fr}} -> langue=fr (explicit is unusual on frwiki but still legitimate)' => [
                '<ref name="2013_www.lfsm.net">{{fr}} [http://www.lfsm.net/8/mia.htm lfsm.net].</ref>',
                'fr',
            ],
            '{{de}} -> langue=de' => [
                "<ref>{{de}} [http://www.buergerstiftung-bonn.de/cms/download/pressetexte/pressemeldung_031115.pdf ''Bürgerstiftung Bonn a déclaré Bücherschrank auf'', Presseerklärung der Bürgerstiftung Bonn, 15. novembre 2003]</ref>",
                'de',
            ],
            '{{pdf}} is a format flag, not a language' => [
                '<ref>{{PDF}} [http://www.chubri-galo.bzh/docs/files/z-ortograf/Regles-orthograph-gallo-moga-05-2016.pdf Règles orthographiques pour le gallo].</ref>',
                null,
            ],
            'no leading template at all' => [
                '<ref>[https://catalogue.bnf.fr/ark:/12148/cb16665235p Bibliothèque Nationale de France]</ref>',
                null,
            ],
        ];
    }

    public function testFrenchLanguageTemplateIsStrippedIntoLeadingTemplatesToo()
    {
        $dto = $this->parser()->parse(
            '<ref name="2013_www.lfsm.net">{{fr}} [http://www.lfsm.net/8/mia.htm lfsm.net].</ref>'
        );

        $this::assertNotNull($dto);
        $this::assertSame(['fr'], $dto->leadingTemplates);
        $this::assertSame('', $dto->leadingText);
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
