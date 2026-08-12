<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink\Raw;

use App\Domain\ExternLink\Raw\Hints\AuthorPrefixExtractor;
use App\Domain\ExternLink\Raw\Hints\ConsulteLeExtractor;
use App\Domain\ExternLink\Raw\Hints\HintExtractorInterface;
use App\Domain\ExternLink\Raw\Hints\ItalicSiteAfterCommaExtractor;
use App\Domain\ExternLink\Raw\Hints\SiteMentionExtractor;
use App\Domain\ExternLink\Raw\Hints\TrailingDateExtractor;

/**
 * Parses a manuscript "[http(s)://... Libellé]" citation fragment (bare, inside a
 * <ref>, or as a "* [url Libellé]" bullet list item) into a RawExternLinkDTO, so a
 * later stage (not implemented here) can decide how much of it can be merged with
 * crawled page metadata into {{lien web}}/{{article}}/{{lien brisé}}.
 *
 * Deliberately pure/stateless : no I/O, no crawling, no template knowledge. Built from
 * a corpus of ~33k real fragments mined via CirrusSearch (see
 * rawExternLinkCorpusScan.php / resources/corpus_raw_extern_link.txt) — the patterns
 * this class recognizes are the ~35% "clean" cases (label is the whole story), leading
 * {{lang}}/{{pdf}} templates (each mapped to a 'langue' hint -- {{fr}} included, see
 * PREFIX_TEMPLATE_PATTERN), French guillemets around the title, an author/institution name
 * right before the bracket ("Louis Laroque, [url ...]"), and — via the Hints/ extractor
 * chain applied to $rest, in order so each can consume what the previous one left — a
 * trailing ", sur Site" mention (~7.6%), a leading ", ''Site''"/", [[Site]]" mention
 * (~33% of the corpus carries italic markup), a trailing date -- plain, {{date|...}}
 * templated, or bare year (~50% of the corpus has a 4-digit year somewhere) -- and a
 * "consulté le" access date (~9.6%, textual or "JJ/MM/AAAA"). Everything else (multi-
 * citation refs, descriptive "sur le site officiel"...) is deliberately left unparsed —
 * see RawExternLinkParserTest for the documented backlog (group "wip").
 *
 * Deliberately silent on {{lien web}} vs {{article}} vs {{lien brisé}} : that choice
 * depends on crawled page metadata (date, DOI, JSON-LD type) and domain config
 * (data_newspapers.json / data_scientific_domain.json), none of which exist at parse
 * time — see ExternRefTransformer::chooseTemplateNameByData(), which the future
 * orchestration layer built on top of this parser must reuse unchanged rather than
 * reimplement. See RawExternLinkParserTest::testParserStaysAgnosticToTemplateChoice().
 */
class RawExternLinkParser
{
    /**
     * Template names recognized as a pure prefix (stripped from $leadingText into
     * $leadingTemplates) when they appear immediately before the bracketed link :
     * language codes (each mapped to a 'langue' hint below, {{fr}} included -- an
     * explicit {{fr}} is unusual on frwiki, since it's the wiki's own default language,
     * but still a legitimate langue=fr worth writing when the editor bothered to add it)
     * plus {{pdf}} (a format flag, never a language). Deliberately narrow : the only ones
     * seen decorating the link itself in the corpus without carrying data of their own
     * ({{Date|}}, {{p.|}} etc. carry data and are left in $rest/$leadingText).
     */
    private const PREFIX_TEMPLATE_PATTERN = '#^\{\{\s*(fr|en|de|es|it|nl|pt|ru|ja|zh|pdf)\s*\}\}\s*#i';

    private const NON_LANGUAGE_PREFIX_TEMPLATES = ['pdf'];

    private const BRACKET_LINK_PATTERN = '#\[(https?://\S+?)(?:\s+([^\]]*))?\]#u';

    /** @var HintExtractorInterface[] applied to $rest, in order */
    private readonly array $hintExtractors;

    /** @var HintExtractorInterface[] applied to $leadingText, in order */
    private readonly array $leadingTextExtractors;

    /**
     * @param HintExtractorInterface[]|null $hintExtractors override the default $rest
     *     chain (tests only) ; null uses the real chain.
     * @param HintExtractorInterface[]|null $leadingTextExtractors override the default
     *     $leadingText chain (tests only) ; null uses the real chain.
     */
    public function __construct(?array $hintExtractors = null, ?array $leadingTextExtractors = null)
    {
        $this->hintExtractors = $hintExtractors ?? [
            new SiteMentionExtractor(),
            new ItalicSiteAfterCommaExtractor(),
            new TrailingDateExtractor(),
            new ConsulteLeExtractor(),
        ];
        $this->leadingTextExtractors = $leadingTextExtractors ?? [
            new AuthorPrefixExtractor(),
        ];
    }

    public function parse(string $fragment): ?RawExternLinkDTO
    {
        $fragment = trim($fragment);

        $refName = null;
        $refGroup = null;
        $isBullet = false;

        if (preg_match('#^<ref((?:\s+[a-zA-Z]+\s*=\s*"[^"]*")*)\s*>(.*)</ref>\s*$#is', $fragment, $refMatch)) {
            $content = $refMatch[2];
            if (preg_match('#\bname\s*=\s*"([^"]*)"#i', $refMatch[1], $m)) {
                $refName = $m[1];
            }
            if (preg_match('#\bgroup\s*=\s*"([^"]*)"#i', $refMatch[1], $m)) {
                $refGroup = $m[1];
            }
        } elseif (preg_match('#^\*\s*(.*)$#s', $fragment, $bulletMatch)) {
            $content = $bulletMatch[1];
            $isBullet = true;
        } else {
            // Bare URL, no wrapper recognized : out of scope, ExternRefTransformer handles it.
            return null;
        }

        if (!preg_match(self::BRACKET_LINK_PATTERN, $content, $linkMatch, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $url = $linkMatch[1][0];
        $label = $linkMatch[2][0] ?? '';
        $before = substr($content, 0, $linkMatch[0][1]);
        $after = substr($content, $linkMatch[0][1] + strlen($linkMatch[0][0]));

        $leadingTemplates = [];
        while (preg_match(self::PREFIX_TEMPLATE_PATTERN, $before, $tplMatch)) {
            $leadingTemplates[] = mb_strtolower($tplMatch[1]);
            $before = substr($before, strlen($tplMatch[0]));
        }

        $titre = $this->stripGuillemets(trim($label));
        [$rest, $restHints] = $this->applyExtractorChain(trim($after), $this->hintExtractors);
        [$leadingText, $leadingHints] = $this->applyExtractorChain(trim($before), $this->leadingTextExtractors);

        return new RawExternLinkDTO(
            raw: $fragment,
            url: $url,
            titre: $titre === '' ? null : $titre,
            leadingText: $leadingText,
            leadingTemplates: $leadingTemplates,
            rest: $rest,
            isBullet: $isBullet,
            refName: $refName,
            refGroup: $refGroup,
            hints: $this->languageHint($leadingTemplates) + $leadingHints + $restHints,
        );
    }

    /**
     * @param HintExtractorInterface[] $extractors
     * @return array{0: string, 1: array<string, string>} [$remainingText, $hints]
     */
    private function applyExtractorChain(string $text, array $extractors): array
    {
        $hints = [];
        foreach ($extractors as $extractor) {
            $match = $extractor->extract($text);
            if ($match === null) {
                continue;
            }
            $hints[$match->param] = $match->value;
            $text = trim($match->remaining);
        }

        return [$text, $hints];
    }

    /**
     * @param string[] $leadingTemplates
     * @return array<string, string>
     */
    private function languageHint(array $leadingTemplates): array
    {
        foreach ($leadingTemplates as $tpl) {
            if (in_array($tpl, self::NON_LANGUAGE_PREFIX_TEMPLATES, true)) {
                continue;
            }

            return ['langue' => $tpl];
        }

        return [];
    }

    /**
     * "« Titre »" -> "Titre". French typographic quotes wrap the title itself in ~14%
     * of the corpus (guillemets), they're not a separate data field to preserve.
     */
    private function stripGuillemets(string $titre): string
    {
        if (preg_match('#^«\s*(.+?)\s*»$#u', $titre, $m)) {
            return $m[1];
        }

        return $titre;
    }
}
