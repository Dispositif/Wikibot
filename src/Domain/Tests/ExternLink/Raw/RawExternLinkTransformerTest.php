<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Tests\ExternLink\Raw;

use App\Domain\ExternLink\ExternRefTransformerInterface;
use App\Domain\ExternLink\Raw\MergeConfidence;
use App\Domain\ExternLink\Raw\RawExternLinkParser;
use App\Domain\ExternLink\Raw\RawExternLinkTransformer;
use App\Domain\Models\Summary;
use App\Domain\WikiTemplateFactory;
use PHPUnit\Framework\TestCase;

/**
 * Lot 4 : RawExternLinkTransformer wires Lot 1-3 (parser + HintMerger) around the
 * EXISTING crawl pipeline. ExternRefTransformer is mocked via its own interface (same
 * style as DeadLinkTransformerTest) : no HTTP involved, no change to
 * ExternRefTransformer's actual code. See RawExternLinkTransformer's docblock for why.
 */
class RawExternLinkTransformerTest extends TestCase
{
    private function transformer(string $crawledResult): RawExternLinkTransformer
    {
        $externRefTransformer = $this->createMock(ExternRefTransformerInterface::class);
        $externRefTransformer->method('process')->willReturn($crawledResult);

        return new RawExternLinkTransformer(new RawExternLinkParser(), $externRefTransformer);
    }

    /**
     * Reads a param back out of a serialized template string, without caring about
     * exact whitespace/param-order formatting -- serialize() has its own normalization
     * quirks (capitalization etc.) that aren't this class's concern.
     */
    private function paramFromSerialized(string $templateName, string $serialized): callable
    {
        $template = WikiTemplateFactory::create($templateName);
        $template->hydrateFromText($serialized);

        return static fn (string $param) => $template->getParam($param);
    }

    public function testReturnsFragmentUnchangedWhenNotABracketedLink()
    {
        $transformer = $this->transformer('should never be reached');

        $result = $transformer->process('<ref>https://x.fr/a</ref>');

        self::assertSame('<ref>https://x.fr/a</ref>', $result->wikitext);
        self::assertSame(MergeConfidence::Skip, $result->confidence);
    }

    public function testReturnsFragmentUnchangedWhenCrawlFails()
    {
        // ExternRefTransformer::process() returns the bare (normalized) URL on any
        // skip/failure path (blacklist, robots.txt, empty metadata, transient error...).
        $transformer = $this->transformer('http://www.presence-pc.com/actualite/batterie-Li-Ion-36696/');

        $fragment = '<ref>[http://www.presence-pc.com/actualite/batterie-Li-Ion-36696/ « Les batteries Li-Ion bientôt dépassées ? »], sur presence-pc.com</ref>';
        $result = $transformer->process($fragment);

        self::assertSame($fragment, $result->wikitext);
        self::assertSame(MergeConfidence::Skip, $result->confidence);
    }

    public function testMergesManuscriptTitleIntoCrawledLienWeb()
    {
        $transformer = $this->transformer(
            '{{lien web |titre=Batteries Li-Ion : le point |url=http://www.presence-pc.com/actualite/batterie-Li-Ion-36696/ |site=presence-pc.com}}'
        );

        $result = $transformer->process(
            '<ref>[http://www.presence-pc.com/actualite/batterie-Li-Ion-36696/ « Les batteries Li-Ion bientôt dépassées ? »], sur presence-pc.com</ref>'
        );

        $get = $this->paramFromSerialized('lien web', $result->wikitext);
        self::assertSame('Les batteries Li-Ion bientôt dépassées ?', $get('titre'), 'manuscript titre wins (editorial field)');
        self::assertSame('presence-pc.com', $get('site'));
        self::assertSame(MergeConfidence::Auto, $result->confidence);
    }

    public function testFlagsSemiAutoOnSiteConflict()
    {
        $transformer = $this->transformer(
            '{{lien web |titre=Bla |url=http://www.presence-pc.com/actualite/batterie-Li-Ion-36696/ |site=Le Figaro}}'
        );

        $result = $transformer->process(
            '<ref>[http://www.presence-pc.com/actualite/batterie-Li-Ion-36696/ « Les batteries Li-Ion bientôt dépassées ? »], sur presence-pc.com</ref>'
        );

        self::assertSame(MergeConfidence::SemiAuto, $result->confidence);
        $get = $this->paramFromSerialized('lien web', $result->wikitext);
        self::assertSame('Le Figaro', $get('site'), 'crawled site kept on conflict, not silently overwritten');
    }

    public function testMergesIntoArticleTemplateToo()
    {
        $transformer = $this->transformer(
            '{{article |titre=Bla |url=http://www.lemonde.fr/x |périodique=Le Monde |date=2019-01-01}}'
        );

        $result = $transformer->process(
            "<ref>Louis Laroque, [http://www.lemonde.fr/x \"Maisons de stars\"], ''Le Monde'', 14 août 2008</ref>"
        );

        $get = $this->paramFromSerialized('article', $result->wikitext);
        self::assertSame('Louis Laroque', $get('auteur1'));
        self::assertSame('14 août 2008', $get('date'), 'manuscript date wins over the crawled one (editorial field)');
    }

    /**
     * Regression (found in live testing, 2026-08-12) : ExternRefTransformer's own
     * optimizer can flag a crawled titre it's unsure about with an HTML comment
     * ("<!-- Vérifiez ce titre -->"). AbstractWikiTemplate::hydrateFromText() refuses to
     * parse ANY text containing a comment it doesn't recognize as its own marker
     * (WikiTextUtil::isCommented()), throwing DomainException -- which used to be
     * silently caught by process()'s try/catch, discarding the whole merge (langue=en
     * from the manuscript's {{en}} prefix went missing, titre reverted to the crawled
     * value despite a perfectly fine manuscript label).
     */
    public function testMergesManuscriptHintsEvenWhenCrawledResultCarriesAWarningComment()
    {
        $transformer = $this->transformer(
            '{{lien web |titre=The Company File - Wells Fargo, Norwest to merge<!-- Vérifiez ce titre --> |url=http://news.bbc.co.uk/2/hi/business/109000.stm |site=bbc.co.uk}}'
        );

        $result = $transformer->process(
            '<ref>{{en}} [http://news.bbc.co.uk/2/hi/business/109000.stm Business: The Company File Wells Fargo, Norwest to merge], BBC, 8 juin 1998</ref>'
        );

        $get = $this->paramFromSerialized('lien web', $result->wikitext);
        self::assertSame('en', $get('langue'), 'the {{en}} manuscript prefix must not be lost');
        self::assertStringContainsString('The Company File Wells Fargo, Norwest to merge', $get('titre'));
    }

    /**
     * Regression (found in live testing, 2026-08-12), same root cause as above but the
     * comment is the WHOLE param value this time (a crawled date the optimizer couldn't
     * parse -- a raw Unix timestamp from that page's JSON-LD, "<!-- 1223032354 -->" =
     * 2008-10-03 -- got wrapped instead of converted). Stripping the comment leaves
     * 'date' empty in the re-parsed crawl data, which is exactly the "gap" the
     * manuscript's own date hint is supposed to fill.
     */
    public function testFillsDateGapWhenCrawledDateIsAnUnparseableCommentedValue()
    {
        $transformer = $this->transformer(
            '{{Article |langue=en |titre=Wells Fargo to Buy Wachovia in $15.1 Billion Deal |périodique=[[The New York Times]] |date=<!-- 1223032354 --> |lire en ligne=http://dealbook.nytimes.com/2008/10/03/wells-fargo-to-merge-with-wachovia/}}'
        );

        $result = $transformer->process(
            '<ref>{{en}} [http://dealbook.nytimes.com/2008/10/03/wells-fargo-to-merge-with-wachovia/ Wells Fargo to Buy Wachovia in $15.1 Billion Deal], 3 octobre 2008</ref>'
        );

        $get = $this->paramFromSerialized('article', $result->wikitext);
        self::assertSame('3 octobre 2008', $get('date'));
        self::assertSame('en', $get('langue'));
    }

    public function testInjectsManuscriptTitleIntoDeadLink()
    {
        $transformer = $this->transformer(
            '{{Lien brisé |url=http://www.saint-maur.com/ |titre=www.saint-maur.com |brisé le=12-03-2026}}'
        );

        $result = $transformer->process(
            '<ref>[http://www.saint-maur.com/ Site de la ville] Consulté le 25 juin 2009</ref>'
        );

        $get = $this->paramFromSerialized('lien brisé', $result->wikitext);
        self::assertSame('Site de la ville', $get('titre'), 'manuscript label replaces the URL-derived placeholder');
        self::assertSame('12-03-2026', $get('brisé le'), "the dead-link check date itself is untouched -- not this class's concern");
        self::assertSame(MergeConfidence::Auto, $result->confidence);
    }

    public function testKeepsDeadLinkUnchangedWhenNoManuscriptTitle()
    {
        // "[http://x.fr/a]" : bracketed but with no label at all -- $raw->titre is null.
        $deadLink = '{{Lien brisé |url=http://x.fr/a |titre=x.fr/a |brisé le=12-03-2026}}';
        $transformer = $this->transformer($deadLink);

        $result = $transformer->process('<ref>[http://x.fr/a]</ref>');

        self::assertSame($deadLink, $result->wikitext);
        self::assertSame(MergeConfidence::Auto, $result->confidence);
    }

    /**
     * Regression (found in prod, 2026-08-13, https://fr.wikipedia.org/w/index.php?title=Odonata&diff=prev&oldid=238601170) :
     * RawExternLinkParser strips the leading "* " off a bullet-list fragment before
     * parsing (so BRACKET_LINK_PATTERN sees the same shape as a bare/<ref> fragment),
     * but nothing ever restored it -- every bullet-list manuscript link lost its bullet,
     * merging it into the following/preceding line.
     */
    public function testKeepsBulletPrefixOnBulletListFragment()
    {
        $transformer = $this->transformer(
            '{{lien web |auteur1=NetPilote SARL |titre=Société française d\'Odonatologie |url=http://www.libellules.org |site=libellules.org}}'
        );

        $result = $transformer->process("* [http://www.libellules.org Société française d'Odonatologie].");

        self::assertStringStartsWith('* {{lien web', $result->wikitext);
    }

    public function testPassesUrlAndSummaryThroughToTheCrawlPipelineUnchanged()
    {
        $externRefTransformer = $this->createMock(ExternRefTransformerInterface::class);
        $summary = new Summary('test');
        $externRefTransformer->expects(self::once())
            ->method('process')
            ->with('http://www.presence-pc.com/actualite/batterie-Li-Ion-36696/', $summary, ['pageTitle' => 'Some Article'])
            ->willReturn('{{lien web |titre=Bla |url=http://www.presence-pc.com/actualite/batterie-Li-Ion-36696/}}');

        $transformer = new RawExternLinkTransformer(new RawExternLinkParser(), $externRefTransformer);
        $transformer->process(
            '<ref>[http://www.presence-pc.com/actualite/batterie-Li-Ion-36696/ « Les batteries Li-Ion bientôt dépassées ? »], sur presence-pc.com</ref>',
            $summary,
            ['pageTitle' => 'Some Article']
        );
    }
}
