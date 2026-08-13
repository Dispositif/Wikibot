<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink\Raw\Hints;

/**
 * "[url Titre], sur Site" -- ~7.6% of the Lot 0 corpus (resources/corpus_raw_extern_link.txt),
 * the single most common named pattern after the "clean, no rest at all" case. Only
 * matches when ", sur X" is the WHOLE rest (nothing trails after the site mention) :
 * that covers the bulk of the corpus bucket ; a site mention followed by more data
 * (date, "consulté le"...) is left to a future extractor to split first, see
 * RawExternLinkParserTest backlog.
 *
 * "sur le site officiel [de X]" is a special case, not a site NAME to extract : it's an
 * editorial judgment call ("this URL IS the subject's own homepage") worth more than
 * whatever the crawl's og:site_name/domain says, so it's recognized as the literal
 * OFFICIAL_SITE_LABEL marker and HintMerger lets it override the crawled value outright
 * instead of running the usual similarity-conflict check against it (2026-08 revision --
 * previously this fell through to the generic branch below, capturing garbage like "le
 * site officiel" as a literal site name and false-conflicting with the real crawled
 * site).
 *
 * "sur le site de X" (no "officiel") is treated as equivalent to "sur X" : the "le site
 * (du/des/de)" wrapper is stripped and X becomes the hint value, going through the exact
 * same downstream handling as a bare "sur X" mention (gap-fill, similarity-compare
 * against the crawled site, or conflict on a real mismatch) -- X itself is not assumed
 * correct, same tolerance the plain "sur X" case already has.
 */
final class SiteMentionExtractor implements HintExtractorInterface
{
    public const OFFICIAL_SITE_LABEL = 'site officiel';

    private const PATTERN = "#^,?\s*sur\s+(.+?)\.?\s*\$#u";

    private const OFFICIAL_SITE_PATTERN = '#\bsite\s+officiel\b#iu';

    /** "(le/son/notre/leur/ce/cet) site (du/des/de) X" -> X. "de" (not "du"/"des") leaves a
     *  following "la "/"l'" article attached to X, since it's not a contraction to strip. */
    private const SITE_OF_PATTERN = "#^(?:le|son|notre|leur|ce|cet)?\s*site\s+(?:du|des|de)\s+(.+)\$#iu";

    public function extract(string $rest): ?HintMatch
    {
        if (trim($rest) === '' || !preg_match(self::PATTERN, $rest, $m)) {
            return null;
        }

        $captured = trim($m[1]);
        if (preg_match(self::OFFICIAL_SITE_PATTERN, $captured) === 1) {
            return new HintMatch('site', self::OFFICIAL_SITE_LABEL, '');
        }

        if (preg_match(self::SITE_OF_PATTERN, $captured, $siteOfMatch) === 1) {
            $captured = trim($siteOfMatch[1]);
        }

        $site = WikiMarkupStripper::stripItalicAndWikilink($captured);
        if ($site === '') {
            return null;
        }

        return new HintMatch('site', $site, '');
    }
}
