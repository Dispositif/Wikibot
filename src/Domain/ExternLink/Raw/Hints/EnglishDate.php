<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink\Raw\Hints;

/**
 * English month names -> number only. Deliberately thin : once a day/month/year is
 * resolved to plain ints, calendar validation and French-text formatting go back
 * through FrenchDate (FrenchDate::isValidCalendarDateByNumber()/monthName()) -- the
 * OUTPUT is always French text ("21 avril 2011"), since these citations end up in
 * {{lien web}}/{{article}} params on fr.wikipedia.org regardless of the source page's
 * own language. Covers "21 April 2011" (day-first, ~611 corpus occurrences of an
 * English month name in ANY position) and "April 21, 2011" (US month-first, comma) --
 * see TrailingDateExtractor/ConsulteLeExtractor for where each shape is recognized.
 */
final class EnglishDate
{
    public const MONTHS_PATTERN = 'january|february|march|april|may|june|july|august|september|october|november|december';

    private const MONTH_NUMBERS
        = [
            'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4, 'may' => 5, 'june' => 6,
            'july' => 7, 'august' => 8, 'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12,
        ];

    public static function monthNumber(string $monthName): ?int
    {
        return self::MONTH_NUMBERS[mb_strtolower($monthName)] ?? null;
    }
}
