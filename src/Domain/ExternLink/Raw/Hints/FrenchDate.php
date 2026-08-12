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
 * fragment, name<->number mapping, and calendar validation via checkdate() --
 * deliberately NOT App\Domain\Utils\DateUtil::simpleFrench2object(), whose
 * DateTime::createFromFormat() is lenient by default and silently rolls "31 février
 * 2019" over into 3 March 2019 instead of rejecting it.
 */
final class FrenchDate
{
    public const MONTHS_PATTERN = 'janvier|février|fevrier|mars|avril|mai|juin|juillet|août|aout|septembre|octobre|novembre|décembre|decembre';

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
}
