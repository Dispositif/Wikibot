<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink\Raw\Hints;

/**
 * "[url Titre], ''Site''" or "[url Titre], [[Site]]" -- ~33% of the Lot 0 corpus
 * (resources/corpus_raw_extern_link.txt) carries italic markup, and this is its most
 * common shape : the site/périodique name right after the comma, in italics and/or
 * wikilinked, most often followed by MORE data (a date, "consulté le"...). Unlike
 * SiteMentionExtractor (which only fires on ", sur X" and requires the whole rest to be
 * consumed), this extractor matches only the leading italic/wikilink span and leaves
 * whatever trails (date, {{date|...}}, "(consulté le ...)") untouched in $rest for a
 * future extractor.
 *
 * The comma is OPTIONAL (2026-08 revision, found via a full-corpus coverage sweep) : a
 * meaningful minority of fragments have the italic/wikilink span right after the
 * bracket with just a space, no comma ("[url Titre] ''Site''. Retrieved..."). Some risk
 * of over-matching prose that happens to be italicized for emphasis rather than naming
 * a site, but the fallback (SemiAuto + 'citation' safety net, see HintMerger) means a
 * wrong guess here is surfaced for review, not silently published.
 *
 * Disjoint from SiteMentionExtractor by construction : this pattern requires the
 * italic/wikilink span to start IMMEDIATELY (no "sur " in between), so "sur ''Site''"
 * (already fully handled by SiteMentionExtractor's own markup stripping) never reaches
 * this class.
 */
final class ItalicSiteAfterCommaExtractor implements HintExtractorInterface
{
    private const PATTERN = "#^,?\s*(''.+?''|\[\[[^\]]+\]\])\s*(.*)\$#us";

    public function extract(string $rest): ?HintMatch
    {
        if (trim($rest) === '' || !preg_match(self::PATTERN, $rest, $m)) {
            return null;
        }

        $site = WikiMarkupStripper::stripItalicAndWikilink($m[1]);
        if ($site === '') {
            return null;
        }

        return new HintMatch('site', $site, $m[2]);
    }
}
