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
 * the single most common named pattern after the "clean, no rest at all" case. Captures
 * "sur X" up to end of string, OR up to a recognized trailing-hint boundary (a
 * "consulté le"/"Retrieved"/"accessed" access date, or a plain citation date, behind a
 * comma/"du"/parenthesis/nothing -- see TRAILING_HINT_LOOKAHEAD) when one shares the
 * same "sur ..." clause with no separator recognized in between (2026-08 revision --
 * previously anchored to end of string only, so "sur X, consulté le DATE", "sur X du
 * DATE", or "sur X (consulté le DATE)" all swallowed the date into the site value
 * whole ; see RawExternLinkParserTest's former "known imprecision" test, now fixed).
 * Whatever follows the boundary is handed back as $remaining for the next extractor in
 * RawExternLinkParser's chain (TrailingDateExtractor/ConsulteLeExtractor) to consume.
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
 * "sur le site [du/des/de] X" (no "officiel") is treated as equivalent to "sur X" : the
 * "le site (du/des/de)" wrapper is stripped and X becomes the hint value, going through
 * the exact same downstream handling as a bare "sur X" mention (gap-fill, similarity-
 * compare against the crawled site, or conflict on a real mismatch) -- X itself is not
 * assumed correct, same tolerance the plain "sur X" case already has. The "du/des/de"
 * connector is optional ("sur le site culture.gouv.fr") : without it, a bare domain
 * directly follows "site".
 */
final class SiteMentionExtractor implements HintExtractorInterface
{
    public const OFFICIAL_SITE_LABEL = 'site officiel';

    private const OFFICIAL_SITE_PATTERN = '#\bsite\s+officiel\b#iu';

    /** "(le/son/notre/leur/ce/cet) site (du/des/de)? X" -> X. "de" (not "du"/"des") leaves
     *  a following "la "/"l'" article attached to X, since it's not a contraction to
     *  strip. The connector is optional : "site culture.gouv.fr" has none. */
    private const SITE_OF_PATTERN = "#^(?:le|son|notre|leur|ce|cet)?\s*site\s+(?:(?:du|des|de)\s+)?(.+)\$#iu";

    /**
     * Zero-width lookahead marking where a same-clause trailing hint begins, so the site
     * capture group above can stop there instead of swallowing it : a "consulté le"/
     * "Retrieved"/"accessed" access date, a {{date|...}} template, a day-first citation
     * date (French or English month name), a month+year date with no day, a US
     * month-first date, or an ISO date -- mirrors ConsulteLeExtractor/
     * TrailingDateExtractor's own trigger patterns closely enough to recognize the
     * boundary, without duplicating their value-resolution logic.
     *
     * The separator before the trigger is itself an optional alternative -- a comma
     * ("sur X, consulté le..."), "du" ("sur X du 2 juillet 2015"), an opening parenthesis
     * with no comma at all ("sur X (consulté le...)"), or nothing (the trigger keyword
     * immediately follows a plain space). Permissive on purpose : the trigger patterns
     * themselves (an explicit "consult/retrieved/accessed" keyword, or a fully-shaped
     * date) are specific enough that requiring a particular punctuation mark in front
     * would just reopen the same swallowing bug for one more corpus variant -- found via
     * a full-corpus sweep counting fragments whose 'site' hint still contained a 4-digit
     * number after the first (comma-only) revision.
     */
    private const TRAILING_HINT_LOOKAHEAD
        = '(?:,\s*|du\s+|\(\s*)?(?:'
        . '(?:[Cc]onsult\S*|[Rr]etrieved|[Aa]ccessed)\b'
        . '|\{\{\s*[Dd]ate\s*\|'
        . '|(?:' . FrenchDate::DAY_PATTERN . ')\s+(?:' . FrenchDate::MONTHS_PATTERN . '|' . EnglishDate::MONTHS_PATTERN . ')\s+\d{4}'
        . '|(?:' . FrenchDate::MONTHS_PATTERN . '|' . EnglishDate::MONTHS_PATTERN . ')\s+\d{4}'
        . '|(?:' . EnglishDate::MONTHS_PATTERN . ')\s+\d{1,2},?\s+\d{4}'
        . '|(?:1[4-9]|20)\d{2}-\d{1,2}-\d{1,2}'
        . ')';

    private const PATTERN
        = '#^,?\s*sur\s+(?<site>.+?)\.?\s*(?=$|' . self::TRAILING_HINT_LOOKAHEAD . ')(?<remaining>.*)$#isu';

    public function extract(string $rest): ?HintMatch
    {
        if (trim($rest) === '' || !preg_match(self::PATTERN, $rest, $m)) {
            return null;
        }

        $captured = trim($m['site']);
        $remaining = $m['remaining'] ?? '';

        if (preg_match(self::OFFICIAL_SITE_PATTERN, $captured) === 1) {
            return new HintMatch('site', self::OFFICIAL_SITE_LABEL, $remaining);
        }

        if (preg_match(self::SITE_OF_PATTERN, $captured, $siteOfMatch) === 1) {
            $captured = trim($siteOfMatch[1]);
        }

        $site = WikiMarkupStripper::stripItalicAndWikilink($captured);
        if ($site === '') {
            return null;
        }

        return new HintMatch('site', $site, $remaining);
    }
}
