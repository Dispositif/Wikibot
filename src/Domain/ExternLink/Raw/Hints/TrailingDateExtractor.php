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
 * {{date|...}}'s param is sometimes "DAY MOIS ANNEE|extra context" (e.g.
 * "{{date|2 août 2020|en astronomie}}" -- "en astronomie" is a display-calendar
 * modifier, not part of the date) or "DAY|MOIS|ANNEE" (3 separate positional params) --
 * resolveTemplateDate() tells those two shapes apart instead of naively joining every
 * pipe-separated segment with a space, which would leak "en astronomie" into the date.
 *
 * Deliberately does NOT touch "Consulté le ..." (a different template param, 'consulté
 * le', not 'date') -- see RawExternLinkParserTest::testExtractsConsulteLeWithoutComma
 * (still wip), naturally disjoint since that phrase never starts with a digit or "{{".
 */
final class TrailingDateExtractor implements HintExtractorInterface
{
    private const MONTHS = 'janvier|février|fevrier|mars|avril|mai|juin|juillet|août|aout|septembre|octobre|novembre|décembre|decembre';

    private const PATTERN = '#^,?\s*(?:\{\{\s*[Dd]ate\s*\|(?<tpl>[^}]+)\}\}|(?<day>\d{1,2})\s+(?<month>' . self::MONTHS . ')\s+(?<year1>(?:1[4-9]|20)\d{2})|(?<year2>(?:1[4-9]|20)\d{2}))\.?\s*(?<remaining>.*)$#iu';

    private const STRICT_DATE_PATTERN = '#^(\d{1,2})\s+(' . self::MONTHS . ')\s+(\d{4})$#iu';

    private const MONTH_NUMBERS
        = [
            'janvier' => 1, 'février' => 2, 'fevrier' => 2, 'mars' => 3, 'avril' => 4,
            'mai' => 5, 'juin' => 6, 'juillet' => 7, 'août' => 8, 'aout' => 8,
            'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'décembre' => 12, 'decembre' => 12,
        ];

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
            $value = $this->resolveTemplateDate($m['tpl']);
        } elseif (!empty($m['day']) && !empty($m['month']) && !empty($m['year1'])) {
            $value = trim($m['day'] . ' ' . $m['month'] . ' ' . $m['year1']);
        } else {
            $value = $m['year2'] ?? null;
        }

        return ($value !== null && $this->looksLikeValidDate($value)) ? $value : null;
    }

    /**
     * {{date|...}}'s single param can be "DAY MOIS ANNEE" (possibly plus a trailing
     * "|context" to discard), or 3 separate "DAY|MOIS|ANNEE" positional params.
     */
    private function resolveTemplateDate(string $tplArgs): ?string
    {
        $parts = array_map('trim', explode('|', $tplArgs));

        if (count($parts) === 3
            && preg_match('#^\d{1,2}$#', $parts[0])
            && preg_match('#^(?:' . self::MONTHS . ')$#iu', $parts[1])
            && preg_match('#^(?:1[4-9]|20)\d{2}$#', $parts[2])
        ) {
            return implode(' ', $parts);
        }

        return $parts[0] !== '' ? $parts[0] : null;
    }

    /**
     * Only the strict "day month year" shape has something to actually calendar-check
     * (checkdate() rejects "31 février"). A bare year, an ordinal day ("1er"), or a
     * "month year" form is kept as-is -- there's nothing to validate a standalone year
     * or an unparsed ordinal against.
     *
     * Deliberately NOT DateUtil::simpleFrench2object() here : DateTime::createFromFormat()
     * is lenient by default and silently rolls "31 février 2019" over into 3 March 2019
     * instead of failing -- checkdate() is the actual calendar validator.
     */
    private function looksLikeValidDate(string $value): bool
    {
        if (preg_match(self::STRICT_DATE_PATTERN, $value, $m) !== 1) {
            return true;
        }

        $month = self::MONTH_NUMBERS[mb_strtolower($m[2])] ?? null;

        return $month !== null && checkdate($month, (int) $m[1], (int) $m[3]);
    }
}
