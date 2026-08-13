<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink\Raw\Hints;

/**
 * "[url Titre], 10 mai 2006", "[url Titre], {{date|31 août 2008}}", "[url Titre],
 * {{Date|11|mars|2015}}" -- ~50% of the Lot 0 corpus has a 4-digit year somewhere and
 * ~31% a French month name. Matches a LEADING date span (day+month+year, month+year, or
 * bare year -- {{date|...}}'s param can be any of the three) right after the optional
 * comma, and leaves whatever trails (most often "(consulté le ...)") untouched in $rest,
 * same non-anchored shape as ItalicSiteAfterCommaExtractor.
 *
 * {{date|...}}'s param shape ("DAY MOIS ANNEE|extra context" vs. 3 separate
 * "DAY|MOIS|ANNEE" positional params) is resolved by the shared
 * FrenchDate::resolveTemplateDateParam() (also used by ConsulteLeExtractor).
 *
 * Deliberately does NOT touch "Consulté le ..." (a different template param, 'consulté
 * le', not 'date' -- see ConsulteLeExtractor), naturally disjoint since that phrase
 * never starts with a digit or "{{".
 */
final class TrailingDateExtractor implements HintExtractorInterface
{
    private const PATTERN = '#^,?\s*(?:\{\{\s*[Dd]ate\s*\|(?<tpl>[^}]+)\}\}|(?<day>\d{1,2})\s+(?<month>' . FrenchDate::MONTHS_PATTERN . ')\s+(?<year1>(?:1[4-9]|20)\d{2})|(?<year2>(?:1[4-9]|20)\d{2}))\.?\s*(?<remaining>.*)$#iu';

    private const STRICT_DATE_PATTERN = '#^(\d{1,2})\s+(' . FrenchDate::MONTHS_PATTERN . ')\s+(\d{4})$#iu';

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
        } elseif (!empty($m['day']) && !empty($m['month']) && !empty($m['year1'])) {
            $value = trim($m['day'] . ' ' . $m['month'] . ' ' . $m['year1']);
        } else {
            $value = $m['year2'] ?? null;
        }

        return ($value !== null && $this->looksLikeValidDate($value)) ? $value : null;
    }

    /**
     * Only the strict "day month year" shape has something to actually calendar-check
     * (FrenchDate::isValidCalendarDate() rejects "31 février"). A bare year, an ordinal
     * day ("1er"), or a "month year" form is kept as-is -- there's nothing to validate a
     * standalone year or an unparsed ordinal against.
     */
    private function looksLikeValidDate(string $value): bool
    {
        if (preg_match(self::STRICT_DATE_PATTERN, $value, $m) !== 1) {
            return true;
        }

        return FrenchDate::isValidCalendarDate((int) $m[1], $m[2], (int) $m[3]);
    }
}
