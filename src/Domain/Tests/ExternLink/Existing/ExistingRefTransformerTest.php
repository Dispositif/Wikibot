<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Tests\ExternLink\Existing;

use App\Domain\ExternLink\Existing\ExistingRefTransformer;
use App\Domain\ExternLink\ExternRefTransformerInterface;
use App\Domain\ExternLink\Raw\MergeConfidence;
use App\Domain\Models\Summary;
use App\Domain\WikiTemplateFactory;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * ExternRefTransformer is mocked via its own interface (same style as
 * RawExternLinkTransformerTest/DeadLinkTransformerTest) : no HTTP involved, no change to
 * ExternRefTransformer's actual code.
 *
 * Unlike RawExternLinkTransformer, ExistingRefTransformer::process() receives the
 * ALREADY-UNWRAPPED <ref> inner content -- exactly what ExistingRefWorker (extending
 * AbstractRefBotWorker) passes it, no <ref>/</ref> tags in the fixtures here (it doesn't
 * need them to disambiguate ref vs. bullet shape the way RawExternLinkParser does).
 */
class ExistingRefTransformerTest extends TestCase
{
    private function transformer(string $crawledResult): ExistingRefTransformer
    {
        $externRefTransformer = $this->createMock(ExternRefTransformerInterface::class);
        $externRefTransformer->method('process')->willReturn($crawledResult);

        return new ExistingRefTransformer($externRefTransformer);
    }

    /**
     * A dead-link crawl also writes into $summary->memo (see ExternHttpErrorLogic/
     * DeadLinkTransformer) -- ExistingRefTransformer tells "went dead" apart from "still
     * alive" by snapshotting those counters, so a mock simulating a dead-link result has
     * to mutate $summary the same way the real pipeline would.
     */
    private function deadLinkTransformer(string $crawledResult, string $memoKey = 'count lien brisé'): ExistingRefTransformer
    {
        $externRefTransformer = $this->createMock(ExternRefTransformerInterface::class);
        $externRefTransformer->method('process')
            ->willReturnCallback(function (string $url, Summary $summary) use ($crawledResult, $memoKey) {
                $summary->memo[$memoKey] = 1 + ($summary->memo[$memoKey] ?? 0);

                return $crawledResult;
            });

        return new ExistingRefTransformer($externRefTransformer);
    }

    private function paramFromSerialized(string $templateName, string $serialized): callable
    {
        $template = WikiTemplateFactory::create($templateName);
        $template->hydrateFromText($serialized);

        return static fn (string $param) => $template->getParam($param);
    }

    public function testReturnsFragmentUnchangedWhenNotAnExistingTemplate()
    {
        $transformer = $this->transformer('should never be reached');

        $result = $transformer->process('https://x.fr/a');

        self::assertSame('https://x.fr/a', $result->refContent);
        self::assertSame(MergeConfidence::Skip, $result->confidence);
    }

    public function testSkipsWhenUrlIsAlreadyAWebArchiveLink()
    {
        $externRefTransformer = $this->createMock(ExternRefTransformerInterface::class);
        $externRefTransformer->expects(self::never())->method('process');
        $transformer = new ExistingRefTransformer($externRefTransformer);

        $fragment = '{{lien web |titre=Bla |url=https://web.archive.org/web/20200101000000/http://x.fr/a |site=x.fr}}';
        $result = $transformer->process($fragment);

        self::assertSame($fragment, $result->refContent);
        self::assertSame(MergeConfidence::Skip, $result->confidence);
    }

    /**
     * Regression (found live, 2026-08-14, "Archivio storico capitolino") : the
     * PRIMARY url was a normal live page (crawl-worthy), but the citation also carried
     * a legitimate 'archive-url' backup field pointing to archive.is. Checked only on
     * the primary url, isWebArchiveUrl() wouldn't have caught this -- the check now
     * scans the whole raw citation.
     */
    public function testSkipsWhenAnyFieldIsAnArchiveTodayLinkEvenIfUrlIsLive()
    {
        $externRefTransformer = $this->createMock(ExternRefTransformerInterface::class);
        $externRefTransformer->expects(self::never())->method('process');
        $transformer = new ExistingRefTransformer($externRefTransformer);

        $fragment = '{{lien web |titre=Bla |url=http://x.fr/a |site=x.fr |archive-url=https://archive.is/20200608121409/http://x.fr/a}}';
        $result = $transformer->process($fragment);

        self::assertSame($fragment, $result->refContent);
        self::assertSame(MergeConfidence::Skip, $result->confidence);
    }

    public function testSkipsWhenUrlIsAnArchiveTodayMirror()
    {
        $externRefTransformer = $this->createMock(ExternRefTransformerInterface::class);
        $externRefTransformer->expects(self::never())->method('process');
        $transformer = new ExistingRefTransformer($externRefTransformer);

        $fragment = '{{lien web |titre=Bla |url=https://archive.ph/20200608121409/http://x.fr/a |site=x.fr}}';
        $result = $transformer->process($fragment);

        self::assertSame($fragment, $result->refContent);
        self::assertSame(MergeConfidence::Skip, $result->confidence);
    }

    public function testReturnsFragmentUnchangedWhenCrawlIsSkipped()
    {
        // ExternRefTransformer::process() returns the bare (normalized) URL on any
        // skip/failure path (blacklist, robots.txt, empty metadata, transient error...).
        $transformer = $this->transformer('http://x.fr/a');

        $fragment = '{{lien web |titre=Bla |url=http://x.fr/a |site=x.fr}}';
        $result = $transformer->process($fragment);

        self::assertSame($fragment, $result->refContent);
        self::assertSame(MergeConfidence::Skip, $result->confidence);
    }

    public function testRefreshesConsulteLeAndKeepsExistingFieldsOnSuccess()
    {
        $transformer = $this->transformer(
            '{{lien web |titre=Titre re-crawlé |url=http://x.fr/a |site=autre-site.fr |consulté le=' . date('d-m-Y') . '}}'
        );

        $fragment = '{{lien web |titre=Titre original |url=http://x.fr/a |site=x.fr |consulté le=01-01-2020}}';
        $result = $transformer->process($fragment);

        $get = $this->paramFromSerialized('lien web', $result->refContent);
        self::assertSame('Titre original', $get('titre'), 'existing curated titre wins over the re-crawled one');
        self::assertSame('x.fr', $get('site'), 'existing curated site wins over the re-crawled one');
        self::assertSame(date('d-m-Y'), $get('consulté le'), 'consulté le is always refreshed to today on a live crawl');
        self::assertSame(MergeConfidence::Auto, $result->confidence);
    }

    public function testFillsEmptyFieldFromCrawlOnSuccess()
    {
        $transformer = $this->transformer(
            '{{lien web |titre=Titre |url=http://x.fr/a |site=x.fr |consulté le=' . date('d-m-Y') . '}}'
        );

        $fragment = '{{lien web |titre=Titre |url=http://x.fr/a}}';
        $result = $transformer->process($fragment);

        $get = $this->paramFromSerialized('lien web', $result->refContent);
        self::assertSame('x.fr', $get('site'), 'missing site is completed from the crawl');
    }

    /**
     * Regression (found live, 2026-08-14, John Moores (homme d'affaires)) : a param
     * this bot's own LienWebTemplate model doesn't recognize (originally 'prénom'/'nom'
     * bare -- since fixed as a real alias, see LienWebTemplate::PARAM_ALIAS -- so a
     * made-up name is used here to keep exercising the general case) but that was
     * already live, human-authored content. A blanket strip of unsupported params
     * applied to the MERGED result was deleting it outright instead of leaving it
     * untouched.
     */
    public function testKeepsExistingUnrecognizedParamsInsteadOfDroppingThem()
    {
        $transformer = $this->transformer(
            '{{lien web |titre=Théroigne de Méricourt |url=http://theconversation.com/x |site=The Conversation |date=2019-01-03 |consulté le=' . date('d-m-Y') . '}}'
        );

        $fragment = '{{Lien web |paramInconnuDuBot=Peter McPhee |titre=Théroigne de Méricourt |url=http://theconversation.com/x |site=The Conversation |date=2019-01-03 |consulté le=2023-12-13}}';
        $result = $transformer->process($fragment);

        self::assertStringContainsString('Peter McPhee', $result->refContent, 'unrecognized existing param must not be silently dropped');
    }

    /**
     * Regression (found live, 2026-08-14, "Ligue 2 : Dunkerque glacé...") : the crawl's
     * JSON-LD only exposes a single combined author name, mapped to 'auteur1'. Merged
     * naively alongside an existing citation's curated 'prénom1'+'nom1' split, BOTH
     * survived into the output -- same author stated twice under different param names.
     */
    public function testDoesNotAddCrawledAuteurWhenExistingAlreadyHasSplitName()
    {
        $transformer = $this->transformer(
            '{{lien web |titre=Titre |url=http://x.fr/a |auteur1=Frédéric Sourice |site=x.fr |consulté le=' . date('d-m-Y') . '}}'
        );

        $fragment = '{{lien web |prénom1=Frédéric |nom1=Sourice |titre=Titre |url=http://x.fr/a |consulté le=2020-01-01}}';
        $result = $transformer->process($fragment);

        $get = $this->paramFromSerialized('lien web', $result->refContent);
        self::assertSame('Frédéric', $get('prénom1'), 'existing split name kept');
        self::assertSame('Sourice', $get('nom1'), 'existing split name kept');
        self::assertNull($get('auteur1'), 'crawled combined name must not be added alongside the existing split');
    }

    /**
     * Symmetric case : existing has the combined 'auteur1', crawl offers a split
     * 'prénom1'/'nom1' for the same slot -- existing wins outright (completion pass,
     * not an upgrade pass), the split must not be added alongside it either.
     */
    public function testDoesNotAddCrawledSplitNameWhenExistingAlreadyHasAuteur()
    {
        $transformer = $this->transformer(
            '{{lien web |titre=Titre |url=http://x.fr/a |prénom1=Frédéric |nom1=Sourice |site=x.fr |consulté le=' . date('d-m-Y') . '}}'
        );

        $fragment = '{{lien web |auteur1=Frédéric Sourice |titre=Titre |url=http://x.fr/a |consulté le=2020-01-01}}';
        $result = $transformer->process($fragment);

        $get = $this->paramFromSerialized('lien web', $result->refContent);
        self::assertSame('Frédéric Sourice', $get('auteur1'), 'existing combined name kept');
        self::assertNull($get('prénom1'), 'crawled split name must not be added alongside the existing combined name');
        self::assertNull($get('nom1'), 'crawled split name must not be added alongside the existing combined name');
    }

    public function testSkipsWhenNothingChanges()
    {
        // 'consulté le' kept old on purpose (not $today) : this exercises the
        // crawl-then-compare no-op path specifically, distinct from the
        // recently-consulted early-skip path covered below.
        $transformer = $this->transformer(
            '{{Lien web |titre=Titre |url=http://x.fr/a |site=x.fr |consulté le=01-01-2020}}'
        );

        // Casing matches LienWebTemplate::WIKITEMPLATE_NAME exactly ("Lien web") : the
        // completion path always re-serializes through that constant, so a differently-
        // cased input would register as a (cosmetic) diff, not a true no-op.
        $fragment = '{{Lien web |titre=Titre |url=http://x.fr/a |site=x.fr |consulté le=01-01-2020}}';
        $result = $transformer->process($fragment);

        self::assertSame(trim($fragment), trim($result->refContent));
    }

    /**
     * The crawl pipeline must not even be called : recently-checked
     * citations are skipped before any HTTP work, not just left unchanged after a crawl.
     */
    public function testSkipsWithoutCrawlingWhenRecentlyConsulted()
    {
        $externRefTransformer = $this->createMock(ExternRefTransformerInterface::class);
        $externRefTransformer->expects(self::never())->method('process');
        $transformer = new ExistingRefTransformer($externRefTransformer);

        $now = new DateTimeImmutable('2026-08-14');
        $fragment = '{{lien web |titre=Titre |url=http://x.fr/a |site=x.fr |consulté le=01-06-2026}}';
        $result = $transformer->process($fragment, new Summary(), [], $now);

        self::assertSame($fragment, $result->refContent);
        self::assertSame(MergeConfidence::Skip, $result->confidence);
    }

    public function testDoesNotSkipWhenConsultedMoreThanOneYearAgo()
    {
        $transformer = $this->transformer(
            '{{lien web |titre=Titre |url=http://x.fr/a |site=x.fr |consulté le=' . date('d-m-Y') . '}}'
        );

        $now = new DateTimeImmutable('2026-08-14');
        $fragment = '{{lien web |titre=Titre |url=http://x.fr/a |site=x.fr |consulté le=01-01-2025}}';
        $result = $transformer->process($fragment, new Summary(), [], $now);

        self::assertSame(date('d-m-Y'), $this->paramFromSerialized('lien web', $result->refContent)('consulté le'));
    }

    public function testDoesNotSkipWhenConsulteLeIsUnparseable()
    {
        $transformer = $this->transformer(
            '{{lien web |titre=Titre |url=http://x.fr/a |site=x.fr |consulté le=' . date('d-m-Y') . '}}'
        );

        $now = new DateTimeImmutable('2026-08-14');
        $fragment = '{{lien web |titre=Titre |url=http://x.fr/a |site=x.fr |consulté le=printemps 2026}}';
        $result = $transformer->process($fragment, new Summary(), [], $now);

        self::assertSame(date('d-m-Y'), $this->paramFromSerialized('lien web', $result->refContent)('consulté le'));
    }

    /**
     * Regression (found live, 2026-08-14, lopinion.fr citation) : a crawl mapped as
     * {{article}} carries the URL under its OWN canonical name 'lire en ligne', which
     * also happens to be a valid alias on {{lien web}} (-> 'url'). Merged against the
     * existing {{lien web}}'s 'url' key without canonicalizing first, hydrate() saw the
     * same URL under two different keys and filed the second one as 'url-doublon'.
     */
    public function testCrawledArticleUrlDoesNotDuplicateOntoExistingLienWeb()
    {
        $transformer = $this->transformer(
            '{{article |titre=Titre |lire en ligne=http://x.fr/a |périodique=Le Monde |auteur1=Jean Dupont |consulté le=' . date('d-m-Y') . '}}'
        );

        $fragment = '{{lien web |titre=Titre |url=http://x.fr/a |site=x.fr}}';
        $result = $transformer->process($fragment);

        self::assertStringNotContainsString('url-doublon', $result->refContent);
    }

    public function testKeepsExistingTemplateTypeOnCompletion()
    {
        // Crawl came back as {{lien web}} (no date found), but the existing citation is
        // an {{article}} -- the established category is kept, not swapped by a re-crawl.
        $transformer = $this->transformer(
            '{{lien web |titre=Titre re-crawlé |url=http://x.fr/a |site=Le Monde}}'
        );

        $fragment = '{{article |titre=Titre |url=http://x.fr/a |périodique=Le Monde |date=2019-01-01}}';
        $result = $transformer->process($fragment);

        self::assertStringStartsWith('{{article', $result->refContent, 'existing template TYPE is kept, not the crawl\'s');
        $get = $this->paramFromSerialized('article', $result->refContent);
        self::assertSame('2019-01-01', $get('date'), 'existing date untouched (article-only field, not in the lien web crawl)');
    }

    public function testConvertsToDeadLinkAndKeepsExistingTitre()
    {
        $transformer = $this->deadLinkTransformer(
            '{{Lien brisé |url=http://x.fr/a |titre=x.fr/a |brisé le=' . date('d-m-Y') . '}}'
        );

        $fragment = '{{lien web |titre=Titre curé par un humain |url=http://x.fr/a |site=x.fr}}';
        $result = $transformer->process($fragment);

        $get = $this->paramFromSerialized('lien brisé', $result->refContent);
        self::assertSame('Titre curé par un humain', $get('titre'), 'existing curated titre replaces the URL-derived placeholder');
        self::assertSame(MergeConfidence::Auto, $result->confidence);
    }

    public function testConvertsToArchivedCopyAndKeepsExistingTitre()
    {
        // DeadLinkTransformer found a webarchive snapshot : the crawl on the archived
        // page can come back as a fresh {{lien web}}, not necessarily {{Lien brisé}}.
        $transformer = $this->deadLinkTransformer(
            '{{lien web |titre=Titre archive |url=http://x.fr/a |site=x.fr via Wikiwix |consulté le=' . date('d-m-Y') . '}}',
            'wikiwix'
        );

        $fragment = '{{lien web |titre=Titre curé par un humain |url=http://x.fr/a |site=x.fr}}';
        $result = $transformer->process($fragment);

        $get = $this->paramFromSerialized('lien web', $result->refContent);
        self::assertSame('Titre curé par un humain', $get('titre'));
        self::assertSame(MergeConfidence::Auto, $result->confidence);
    }

    /**
     * Regression (found live, 2026-08-14) : 'brisé le' on an archived replacement makes
     * MediaWiki render a perfectly working {{lien web}} as broken. Archive found => plain
     * refresh (consulté le = today), no 'brisé le' -- unlike a genuine {{Lien brisé}},
     * see below.
     */
    public function testArchivedReplacementGetsPlainConsulteLeRefreshNoBriseLe()
    {
        $transformer = $this->deadLinkTransformer(
            '{{lien web |titre=Titre archive |url=https://web.archive.org/web/20211111013456/http://x.fr/a |site=x.fr via Wikiwix |consulté le=' . date('d-m-Y') . '}}',
            'wikiwix'
        );

        $now = new DateTimeImmutable('2026-08-14');
        $fragment = '{{lien web |titre=Titre |url=http://x.fr/a |site=x.fr |consulté le=20-06-2023}}';
        $result = $transformer->process($fragment, new Summary(), [], $now);

        $get = $this->paramFromSerialized('lien web', $result->refContent);
        self::assertSame(date('d-m-Y'), $get('consulté le'), 'consulté le refreshed to today, old date not kept here');
        self::assertNull($get('brisé le'), 'no brisé le on a working archived replacement');
    }

    public function testGenuineLienBriseKeepsOldConsulteLeAndSetsBriseLeToNow()
    {
        $transformer = $this->deadLinkTransformer(
            '{{Lien brisé |url=http://x.fr/a |titre=x.fr/a |brisé le=' . date('d-m-Y') . '}}'
        );

        $now = new DateTimeImmutable('2026-08-14');
        $fragment = '{{lien web |titre=Titre |url=http://x.fr/a |site=x.fr |consulté le=20-06-2023}}';
        $result = $transformer->process($fragment, new Summary(), [], $now);

        $get = $this->paramFromSerialized('lien brisé', $result->refContent);
        self::assertSame('20-06-2023', $get('consulté le'), 'old consulté le kept as last-confirmed-alive date');
        self::assertSame('14-08-2026', $get('brisé le'), 'brisé le is today, not the crawl\'s own now()');
    }

    public function testPassesUrlAndSummaryThroughToTheCrawlPipelineUnchanged()
    {
        $externRefTransformer = $this->createMock(ExternRefTransformerInterface::class);
        $summary = new Summary('test');
        $externRefTransformer->expects(self::once())
            ->method('process')
            ->with('http://x.fr/a', $summary, ['pageTitle' => 'Some Article'])
            ->willReturn('{{lien web |titre=Bla |url=http://x.fr/a}}');

        $transformer = new ExistingRefTransformer($externRefTransformer);
        $transformer->process(
            '{{lien web |titre=Bla |url=http://x.fr/a}}',
            $summary,
            ['pageTitle' => 'Some Article']
        );
    }
}
