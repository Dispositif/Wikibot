<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink\Raw\Hints;

/**
 * "[url Titre], 10 mai 2006", "[url Titre], 10 May 2006", "[url Titre], May 10, 2006",
 * "[url Titre], {{date|31 août 2008}}", "[url Titre], {{Date|11|mars|2015}}" -- ~50% of
 * the Lot 0 corpus has a 4-digit year somewhere and ~31% a French month name (a further
 * 611 occurrences of an English month name in some position, day-first "10 May 2006" or
 * US month-first "May 10, 2006"). Matches a LEADING date span (day+month+year, month+year,
 * or bare year -- {{date|...}}'s param can be any of the three) right after the optional
 * comma, and leaves whatever trails (most often "(consulté le ...)") untouched in $rest,
 * same non-anchored shape as ItalicSiteAfterCommaExtractor.
 *
 * ALWAYS resolves to French text output ("10 mai 2006"), regardless of how the source
 * page phrased its own date -- this is a French Wikipedia citation param.
 *
 * {{date|...}}'s param shape ("DAY MOIS ANNEE|extra context" vs. 3 separate
 * "DAY|MOIS|ANNEE" positional params) is resolved by the shared
 * FrenchDate::resolveTemplateDateParam() (also used by ConsulteLeExtractor) -- French
 * only, {{date|...}} is a French wiki template, never seen with an English month.
 *
 * Deliberately does NOT touch "Consulté le ..."/"Retrieved ..." (a different template
 * param, 'consulté le', not 'date' -- see ConsulteLeExtractor), naturally disjoint since
 * that phrase never starts with a digit, a month name, or "{{".
 */
final class TrailingDateExtractor implements HintExtractorInterface
{
    private const MONTHS_PATTERN = FrenchDate::MONTHS_PATTERN . '|' . EnglishDate::MONTHS_PATTERN;

    private const YEAR_PATTERN = '(?:1[4-9]|20)\d{2}';

    private const PATTERN
        = '#^,?\s*(?:'
        . '\{\{\s*[Dd]ate\s*\|(?<tpl>[^}]+)\}\}'
        . '|(?<day>' . FrenchDate::DAY_PATTERN . ')\s+(?<month>' . self::MONTHS_PATTERN . ')\s+(?<year1>' . self::YEAR_PATTERN . ')'
        . '|(?<usmonth>' . EnglishDate::MONTHS_PATTERN . ')\s+(?<usday>\d{1,2}),?\s+(?<usyear>' . self::YEAR_PATTERN . ')'
        . '|(?<isoyear>' . self::YEAR_PATTERN . ')-(?<isomonth>\d{1,2})-(?<isoday>\d{1,2})'
        . '|(?<year2>' . self::YEAR_PATTERN . ')'
        . ')\.?\s*(?<remaining>.*)$#iu';

    private const STRICT_DATE_PATTERN = '#^(' . FrenchDate::DAY_PATTERN . ')\s+(' . FrenchDate::MONTHS_PATTERN . ')\s+(\d{4})$#iu';

    public function extract(string $rest): ?HintMatch
    {
        if (trim($rest) === '' || !preg_match(self::PATTERN, $rest, $m)) {
            return null;
        }

        $value = $this->resolveValue($m);
        if ($value === null) {
            return null;
        }

        return new HintMatch('date', $value, $m['remaining'] ?? '');
    }

    private function resolveValue(array $m): ?string
    {
        if (!empty($m['tpl'])) {
            $value = FrenchDate::resolveTemplateDateParam($m['tpl']);

            return ($value !== null && $this->looksLikeValidFrenchDate($value)) ? $value : null;
        }

        if (!empty($m['day']) && !empty($m['month']) && !empty($m['year1'])) {
            return $this->resolveDayFirst($m['day'], $m['month'], (int) $m['year1']);
        }

        if (!empty($m['usmonth'])) {
            return $this->resolveNumeric((int) $m['usday'], EnglishDate::monthNumber($m['usmonth']), (int) $m['usyear']);
        }

        if (!empty($m['isoyear'])) {
            return $this->resolveNumeric((int) $m['isoday'], (int) $m['isomonth'], (int) $m['isoyear']);
        }

        // Bare year : nothing to calendar-check against.
        return $m['year2'] ?? null;
    }

    /**
     * Day-first shape, month can be French OR English ("10 mai 2006" / "10 May 2006") --
     * unlike resolveNumeric()'s branches, keeps the ordinal spelling ("1er") rather than
     * a plain numeric day when the source used one, since that's a French-specific
     * convention worth preserving verbatim.
     */
    private function resolveDayFirst(string $dayRaw, string $monthRaw, int $year): ?string
    {
        $day = FrenchDate::dayNumber($dayRaw);
        $month = FrenchDate::monthNumber($monthRaw) ?? EnglishDate::monthNumber($monthRaw);
        if ($month === null || !FrenchDate::isValidCalendarDateByNumber($day, $month, $year)) {
            return null;
        }

        $monthName = FrenchDate::monthNumber($monthRaw) !== null ? $monthRaw : FrenchDate::monthName($month);

        return trim(FrenchDate::dayText($dayRaw) . ' ' . $monthName . ' ' . $year);
    }

    private function resolveNumeric(int $day, ?int $month, int $year): ?string
    {
        if ($month === null || !FrenchDate::isValidCalendarDateByNumber($day, $month, $year)) {
            return null;
        }

        return FrenchDate::toFrenchText($day, $month, $year);
    }

    /**
     * Only the strict "day month year" shape has something to actually calendar-check
     * (FrenchDate::isValidCalendarDate() rejects "31 février"). A bare year or a
     * "month year" form is kept as-is -- there's nothing to validate a standalone year
     * against. French month names only : this only ever validates a {{date|...}}
     * template's resolved text, and that template is French-authored.
     */
    private function looksLikeValidFrenchDate(string $value): bool
    {
        if (preg_match(self::STRICT_DATE_PATTERN, $value, $m) !== 1) {
            return true;
        }

        return FrenchDate::isValidCalendarDate(FrenchDate::dayNumber($m[1]), $m[2], (int) $m[3]);
    }
}
