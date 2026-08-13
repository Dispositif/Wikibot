<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink\Raw\Hints;

/**
 * "[url Titre] Consulté le 06/07/2009.", "[url Titre] (consulté le 23 janvier 2009).",
 * "[url Titre] Retrieved: 21 April 2011.", "[url Titre] Retrieved December 9, 2011." --
 * ~9.6% of the corpus, ~2.1% of it with no comma before it, which is why it needs its
 * own extractor rather than piggybacking on the comma-based ones : ordering after
 * TrailingDateExtractor in the chain means it also picks up the common "[url Titre],
 * 30 juin 2011 (consulté le 6 avril 2020)." shape, where the citation date is consumed
 * first and this extractor only sees what's left, "(consulté le 6 avril 2020).".
 *
 * "Retrieved"/"retrieved on"/"accessed" (157 "Retrieved" occurrences alone in the Lot 0
 * corpus) are the English equivalent of "consulté le" -- unlike the French forms, the
 * connector word ("on") is often absent entirely ("Retrieved December 9, 2011"), so it's
 * OPTIONAL here, not mandatory. All four date shapes below always resolve to French text
 * output ("21 avril 2011") regardless of how the source page phrased it, since this is a
 * French Wikipedia citation param :
 * - day-first, French or English month name : "21 avril 2011" / "21 April 2011"
 * - US month-first with comma : "April 21, 2011"
 * - ISO : "2011-04-21" ("Retrieved 2011-04-21", a real corpus shape)
 * - numeric DD/MM/YYYY : "06/07/2009" (French convention, never MM/DD)
 * - numeric MM/YYYY, no day : "03/2024" ("''consulté le 03/2024''", a real corpus shape)
 * - {{date|...}} template (see the shared FrenchDate::resolveTemplateDateParam(),
 *   also used by TrailingDateExtractor) and the ordinal placeholder {{1er}} (see
 *   FrenchDate::DAY_PATTERN).
 *
 * Tolerates an optional wrapping "''...''" (italics) around the whole access-date
 * phrase (2026-08 revision) : ItalicSiteAfterCommaExtractor, earlier in the chain,
 * deliberately backs off an italic span that itself looks like an access date
 * ("[url Titre], ''consulté le 03/2024''") rather than misreading it as a site name,
 * leaving the markup-wrapped phrase for this extractor to recognize.
 */
final class ConsulteLeExtractor implements HintExtractorInterface
{
    private const MONTHS_PATTERN = FrenchDate::MONTHS_PATTERN . '|' . EnglishDate::MONTHS_PATTERN;

    private const PATTERN
        = '#^,?\s*\(?\s*(?:\'\')?\s*(?:[Cc]onsult\S*|[Rr]etrieved|[Aa]ccessed)\s*:?\s*(?:(?:le|du|on|sur\s+\S+\s+le)\s+)?(?:'
        . '\{\{\s*[Dd]ate\s*\|(?<tpl>[^}]+)\}\}'
        . '|(?<textday>' . FrenchDate::DAY_PATTERN . ')\s+(?<textmonth>' . self::MONTHS_PATTERN . ')\s+(?<textyear>\d{4})'
        . '|(?<usmonth>' . EnglishDate::MONTHS_PATTERN . ')\s+(?<usday>\d{1,2}),?\s+(?<usyear>\d{4})'
        . '|(?<isoyear>\d{4})-(?<isomonth>\d{1,2})-(?<isoday>\d{1,2})'
        . '|(?<numday>\d{1,2})[/.\-](?<nummonth>\d{1,2})[/.\-](?<numyear>\d{2,4})'
        . '|(?<nummonthonly>\d{1,2})/(?<numyearonly>\d{4})'
        . ')(?:\'\')?\)?\.?\s*(?<remaining>.*)$#iu';

    public function extract(string $rest): ?HintMatch
    {
        if (trim($rest) === '' || !preg_match(self::PATTERN, $rest, $m)) {
            return null;
        }

        $value = $this->resolveValue($m);
        if ($value === null) {
            return null;
        }

        return new HintMatch('consulté le', $value, $m['remaining'] ?? '');
    }

    private function resolveValue(array $m): ?string
    {
        if (!empty($m['tpl'])) {
            return FrenchDate::resolveTemplateDateParam($m['tpl']);
        }

        if (!empty($m['textday'])) {
            $day = FrenchDate::dayNumber($m['textday']);
            $month = FrenchDate::monthNumber($m['textmonth']) ?? EnglishDate::monthNumber($m['textmonth']);
            $year = (int) $m['textyear'];
            if ($month === null || !FrenchDate::isValidCalendarDateByNumber($day, $month, $year)) {
                return null;
            }

            // Keep the ordinal spelling ("1er") rather than the generic toFrenchText()'s
            // numeric day -- only meaningful for the French-worded branch ; the US/ISO/
            // numeric branches below have no such convention to preserve.
            $monthName = FrenchDate::monthNumber($m['textmonth']) !== null ? $m['textmonth'] : FrenchDate::monthName($month);

            return trim(FrenchDate::dayText($m['textday']) . ' ' . $monthName . ' ' . $year);
        }

        if (!empty($m['usmonth'])) {
            return $this->toValidatedFrenchText(
                (int) $m['usday'],
                EnglishDate::monthNumber($m['usmonth']),
                (int) $m['usyear']
            );
        }

        if (!empty($m['isoyear'])) {
            return $this->toValidatedFrenchText((int) $m['isoday'], (int) $m['isomonth'], (int) $m['isoyear']);
        }

        if (!empty($m['numday'])) {
            $year = (int) $m['numyear'];
            if ($year < 100) {
                $year += ($year < 70) ? 2000 : 1900;
            }

            return $this->toValidatedFrenchText((int) $m['numday'], (int) $m['nummonth'], $year);
        }

        if (!empty($m['nummonthonly'])) {
            $monthName = FrenchDate::monthName((int) $m['nummonthonly']);

            return $monthName === null ? null : $monthName . ' ' . $m['numyearonly'];
        }

        return null;
    }

    private function toValidatedFrenchText(int $day, ?int $month, int $year): ?string
    {
        if ($month === null || !FrenchDate::isValidCalendarDateByNumber($day, $month, $year)) {
            return null;
        }

        return FrenchDate::toFrenchText($day, $month, $year);
    }
}
