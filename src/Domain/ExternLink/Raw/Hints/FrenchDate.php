<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink\Raw\Hints;

/**
 * Shared by TrailingDateExtractor and ConsulteLeExtractor : French month name regex
 * fragment, name<->number mapping, calendar validation via checkdate() -- deliberately
 * NOT App\Domain\Utils\DateUtil::simpleFrench2object(), whose DateTime::createFromFormat()
 * is lenient by default and silently rolls "31 février 2019" over into 3 March 2019
 * instead of rejecting it -- and {{date|...}} template param resolution.
 */
final class FrenchDate
{
    public const MONTHS_PATTERN = 'janvier|février|fevrier|mars|avril|mai|juin|juillet|août|aout|septembre|octobre|novembre|décembre|decembre';

    /** "{{1er}}" as a standalone day placeholder (seen preceding a plain month/year, e.g.
     *  "consulté le {{1er}} avril 2012") -- normalizes to the ordinal text "1er". */
    public const ORDINAL_FIRST_DAY_PATTERN = '\{\{\s*1er\s*\}\}';

    /**
     * Every shape a day-of-month is written in : plain "12"/"01", the French ordinal
     * "1er" (86 occurrences in the Lot 0 corpus, only ever for the 1st), or that same
     * ordinal wrapped in its template, "{{1er}}" (195 occurrences).
     */
    public const DAY_PATTERN = '\d{1,2}(?:er)?|' . self::ORDINAL_FIRST_DAY_PATTERN;

    private const MONTH_NUMBERS
        = [
            'janvier' => 1, 'février' => 2, 'fevrier' => 2, 'mars' => 3, 'avril' => 4,
            'mai' => 5, 'juin' => 6, 'juillet' => 7, 'août' => 8, 'aout' => 8,
            'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'décembre' => 12, 'decembre' => 12,
        ];

    private const MONTH_NAMES
        = [
            1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril', 5 => 'mai', 6 => 'juin',
            7 => 'juillet', 8 => 'août', 9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
        ];

    public static function monthNumber(string $monthName): ?int
    {
        return self::MONTH_NUMBERS[mb_strtolower($monthName)] ?? null;
    }

    public static function monthName(int $monthNumber): ?string
    {
        return self::MONTH_NAMES[$monthNumber] ?? null;
    }

    public static function isValidCalendarDate(int $day, string $monthName, int $year): bool
    {
        $month = self::monthNumber($monthName);

        return $month !== null && checkdate($month, $day, $year);
    }

    /**
     * Same calendar check, for callers (English/US/ISO date branches in
     * TrailingDateExtractor/ConsulteLeExtractor) that already resolved the month to a
     * number themselves via EnglishDate::monthNumber() rather than a French name.
     */
    public static function isValidCalendarDateByNumber(int $day, int $month, int $year): bool
    {
        return checkdate($month, $day, $year);
    }

    /** "21 April 2011"/"April 21, 2011"/"2011-04-21" all become "21 avril 2011" : a
     *  French citation param should read in French regardless of the source's language. */
    public static function toFrenchText(int $day, int $month, int $year): ?string
    {
        $monthName = self::monthName($month);

        return $monthName === null ? null : $day . ' ' . $monthName . ' ' . $year;
    }

    /**
     * "1er"/"{{1er}}" -> 1 for calendar validation ; any other value parsed as an int
     * (non-numeric -> 0, which simply fails checkdate() rather than throwing).
     */
    public static function dayNumber(string $day): int
    {
        return self::isOrdinalFirstDay($day) ? 1 : (int) $day;
    }

    /** "{{1er}}" -> "1er" (the literal text a human would have written without the
     *  template wrapper) ; any other value passed through unchanged. */
    public static function dayText(string $day): string
    {
        return self::isOrdinalFirstDay($day) ? '1er' : $day;
    }

    private static function isOrdinalFirstDay(string $day): bool
    {
        return preg_match('#^(?:' . self::ORDINAL_FIRST_DAY_PATTERN . '|1er)$#iu', trim($day)) === 1;
    }

    /**
     * {{date|...}}'s single param can be "DAY MOIS ANNEE" (possibly plus a trailing
     * "|context" to discard, e.g. "{{date|2 août 2020|en astronomie}}" -- "en astronomie"
     * is a display-calendar modifier, not part of the date), or 3 separate
     * "DAY|MOIS|ANNEE" positional params (e.g. "{{Date|11|mars|2015}}"). Tells the two
     * shapes apart instead of naively joining every pipe-separated segment with a space,
     * which would leak the trailing context into the date.
     */
    public static function resolveTemplateDateParam(string $tplArgs): ?string
    {
        $parts = array_map('trim', explode('|', $tplArgs));

        if (count($parts) === 3
            && preg_match('#^\d{1,2}$#', $parts[0])
            && preg_match('#^(?:' . self::MONTHS_PATTERN . ')$#iu', $parts[1])
            && preg_match('#^(?:1[4-9]|20)\d{2}$#', $parts[2])
        ) {
            return implode(' ', $parts);
        }

        return $parts[0] !== '' ? $parts[0] : null;
    }
}
