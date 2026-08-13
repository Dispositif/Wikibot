<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink\Raw;

use App\Domain\ExternLink\ExternRefTransformerInterface;
use App\Domain\Models\Summary;
use App\Domain\Models\Wiki\AbstractWikiTemplate;
use App\Domain\WikiOptimizer\OptimizerFactory;
use App\Domain\WikiTemplateFactory;
use Throwable;

/**
 * Orchestrates Lot 1-3 (RawExternLinkParser + HintMerger) around the EXISTING,
 * UNCHANGED crawl pipeline (ExternRefTransformer, injected here only via its
 * interface) : "[url Libellé]" -> crawl the bare $raw->url exactly like extern-ref
 * already does -> merge the manuscript hints into whatever template that produced.
 *
 * Deliberately does NOT modify ExternRefTransformer/DeadLinkTransformer at all --
 * $externRefTransformer->process() is called completely unchanged, and its serialized
 * output is parsed back (AbstractWikiTemplate::hydrateFromText()/toArray(), the same
 * round-trip LienWebTemplateTest already relies on) rather than intercepting the crawl
 * pipeline's internals. Safer : zero risk of regressing a class with no direct unit
 * tests that's actively running in prod (goo-extern/last-extern-ref/extern-ref).
 *
 * Known simplification, not attempted here : a manuscript 'date' hint that fills a gap
 * could legitimately upgrade {{lien web}} to {{article}} (ExternRefTransformer's own
 * "date obligatoire pour {article}" rule), but chooseTemplateNameByData() is protected
 * and not reused here -- the template TYPE the crawl already picked is kept as-is, only
 * its params are merged. Backlog item, not a bug : see
 * audits/audit-backlog-ameliorations-2026-08.md.
 */
final class RawExternLinkTransformer
{
    public function __construct(
        private readonly RawExternLinkParser $parser,
        private readonly ExternRefTransformerInterface $externRefTransformer,
        private readonly HintMerger $merger = new HintMerger(),
    ) {
    }

    public function process(string $fragment, Summary $summary = new Summary(), array $options = []): RawExternLinkResult
    {
        $raw = $this->parser->parse($fragment);
        if ($raw === null) {
            // Not a bracketed link (bare URL, or no wrapper recognized at all) : out of
            // RawExternLinkParser's scope entirely, ExternRefTransformer handles it directly.
            return new RawExternLinkResult($fragment, MergeConfidence::Skip);
        }

        $crawled = trim($this->externRefTransformer->process($raw->url, $summary, $options));

        if (!str_starts_with($crawled, '{{')) {
            // Crawl failed or was skipped (blacklist, robots.txt, empty metadata, a
            // transient HTTP error...) : ExternRefTransformer already returned the bare
            // URL in these cases. "Zero data loss" invariant : don't fabricate a
            // citation from manuscript hints alone without a real crawl backing it.
            return new RawExternLinkResult($fragment, MergeConfidence::Skip);
        }

        $templateName = $this->extractTemplateName($crawled);
        if ($templateName === null) {
            return new RawExternLinkResult($fragment, MergeConfidence::Skip);
        }

        try {
            $result = $this->isLienBrise($templateName)
                ? new RawExternLinkResult($this->injectManuscriptTitle($crawled, $templateName, $raw), MergeConfidence::Auto)
                : $this->mergeIntoTemplate($crawled, $templateName, $raw);
        } catch (Throwable) {
            // Malformed/unexpected template text (shouldn't happen -- $crawled came from
            // ExternRefTransformer itself) : fail safe, keep the crawl's own result.
            $result = new RawExternLinkResult($crawled, MergeConfidence::SemiAuto);
        }

        // The parser strips the leading "* " off a bullet-list fragment before parsing
        // (RawExternLinkParser::parse()) so BRACKET_LINK_PATTERN sees the same shape as a
        // bare/<ref> fragment -- restore it here, since every path above serializes only
        // the template itself.
        return $raw->isBullet ? new RawExternLinkResult('* ' . $result->wikitext, $result->confidence) : $result;
    }

    private function extractTemplateName(string $serialized): ?string
    {
        if (preg_match('#^\{\{\s*([^|}]+)#u', $serialized, $m) !== 1) {
            return null;
        }

        return trim($m[1]);
    }

    private function isLienBrise(string $templateName): bool
    {
        return in_array(mb_strtolower($templateName), ['lien brisé', 'lien brise'], true);
    }

    /**
     * {{Lien brisé}} carries a URL-derived placeholder titre (DeadLinkTransformer's
     * generateTitleFromURLText()) -- a manuscript label, when there is one, is always a
     * better titre than that.
     */
    private function injectManuscriptTitle(string $crawled, string $templateName, RawExternLinkDTO $raw): string
    {
        if ($raw->titre === null) {
            return $crawled;
        }

        $data = $this->hydrateFromSerialized($templateName, $crawled)->toArray();
        $data['titre'] = $raw->titre;

        return $this->freshTemplate($templateName, $data)->serialize(true);
    }

    private function mergeIntoTemplate(string $crawled, string $templateName, RawExternLinkDTO $raw): RawExternLinkResult
    {
        $crawledMapData = $this->hydrateFromSerialized($templateName, $crawled)->toArray();
        $mergeResult = $this->merger->merge($raw, $crawledMapData);

        // e.g. 'site' has no place on {{article}} (its equivalent, 'périodique', is
        // already filled by the same manuscript hint) : hydrating it anyway would
        // serialize as a bot-authored "PARAMETRE N'EXISTE PAS" HTML comment, exactly
        // what ExternRefTransformer::stripParamsNotSupportedByTemplate() exists to avoid.
        $data = $this->stripParamsNotSupportedByTemplate($mergeResult->mapData, $templateName);

        $template = $this->freshTemplate($templateName, $data);
        $optimizer = OptimizerFactory::fromTemplate($template);
        if ($optimizer !== null) {
            $optimizer->doTasks();
            $template = $optimizer->getOptiTemplate();
        }

        return new RawExternLinkResult($template->serialize(true), $mergeResult->confidence);
    }

    /**
     * The crawl's own optimizer can leave an HTML comment in its output (e.g. "<!--
     * Vérifiez ce titre -->" flagging an uncertain title) -- AbstractWikiTemplate::
     * hydrateFromText() refuses to parse text containing ANY comment it doesn't
     * recognize as its own "Paramètre obligatoire" marker (WikiTextUtil::isCommented(),
     * a guard against double-processing an already-flagged param), throwing
     * DomainException. Stripping comments first means that flag is lost on re-parse --
     * acceptable here since 'titre' (the usual target of such a flag) is what the
     * manuscript-wins merge overwrites in the common case anyway ; the alternative,
     * silently falling through to the un-merged crawl result (this method's caller
     * catches Throwable), is worse -- it drops the manuscript hints entirely, which is
     * exactly what happened before this was added (langue=en from an {{en}} prefix went
     * missing whenever the crawled titre had already been flagged).
     */
    private function hydrateFromSerialized(string $templateName, string $serialized): AbstractWikiTemplate
    {
        $serialized = preg_replace('#<!--.*?-->#s', '', $serialized) ?? $serialized;

        $template = WikiTemplateFactory::create($templateName);
        $template->hydrateFromText($serialized);

        return $template;
    }

    private function stripParamsNotSupportedByTemplate(array $mapData, string $templateName): array
    {
        $probe = WikiTemplateFactory::create($templateName);

        return array_intersect_key($mapData, array_flip($probe->getParamsAndAlias()));
    }

    private function freshTemplate(string $templateName, array $data): AbstractWikiTemplate
    {
        $template = WikiTemplateFactory::create($templateName);
        $template->userSeparator = ' |';
        $template->hydrate($data);

        return $template;
    }
}
