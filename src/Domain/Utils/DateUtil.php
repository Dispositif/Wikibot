<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2023 © Philippe/Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Utils;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;

/**
 * see TYPO https://fr.wikipedia.org/wiki/Wikip%C3%A9dia:AutoWikiBrowser/Typos#Dates
 * Class DateUtil.
 */
class DateUtil
{
    /**
     * "09 mai 2019" => DateTime
     */
    public static function simpleFrench2object(string $string): ?DateTime
    {
        $string = self::french2English(trim($string));
        $dateTime = DateTime::createFromFormat('d F Y', $string);

        return $dateTime ?: null;
    }

    /**
     * Parses a wiki template date parameter value ("date", "consulté le", "brisé
     * le"...) into a DateTimeImmutable. Formats seen in the wild : this bot's own
     * "d-m-Y" (DeadLinkTransformer/mappers), plain ISO "Y-m-d" (common on
     * machine-imported citations), "d/m/Y", and French long-form ("13 décembre 2023",
     * via simpleFrench2object()). Extracted from ExistingRefTransformer::
     * parseConsulteLe() (2026-08), which needed the exact same cascade for its own
     * "recently consulted" skip check and now delegates here.
     *
     * The round-trip format()===$value check on the numeric formats guards against
     * DateTime::createFromFormat()'s lenient overflow (e.g. silently rolling
     * "31-02-2023" into a different, valid date) -- same rationale as FrenchDate's own
     * docblock. simpleFrench2object() itself has no such guard (documented there).
     *
     * Returns null on failure, never throws -- callers treat an unparseable/missing
     * date as "unknown", not as "recent" or "now".
     */
    public static function parseTemplateDate(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y'] as $format) {
            $date = DateTime::createFromFormat('!' . $format, $value);
            if ($date instanceof DateTime && $date->format($format) === $value) {
                return DateTimeImmutable::createFromMutable($date);
            }
        }

        $french = self::simpleFrench2object($value);

        return $french instanceof DateTime ? DateTimeImmutable::createFromMutable($french) : null;
    }

    /**
     * "01 janvier 2020 à 17:44 (CET)" => DateTime.
     */
    public static function fromWikiSignature(string $string): ?DateTime
    {
        // 1 janvier 2020 à 17:44 (CET) => 1 January 2020 à 17:44 (CET)
        $string = self::french2English(trim($string));

        $timezone = new DateTimeZone('Europe/Paris'); // CET, CEST
        if (preg_match('/\(UTC\)/', (string) $string)) {
            $timezone = new DateTimeZone('UTC');
        }
        // strip "(CET)", "(CEST)", "(UTC)"...
        $string = preg_replace('/\([A-Z]{3,4}\)/', '', (string) $string);
        // convert fuseau ? https://stackoverflow.com/questions/5746531/utc-date-time-string-to-timezone

        return DateTime::createFromFormat('d F Y \à H\:i', trim($string), $timezone) ?: null;
    }

    public static function english2french(string $dateStr): string
    {
        return str_replace(
            [
                'January',
                'February',
                'March',
                'April',
                'May',
                'June',
                'July',
                'August',
                'September',
                'October',
                'November',
                'December',
            ],
            [
                'janvier',
                'février',
                'mars',
                'avril',
                'mai',
                'juin',
                'juillet',
                'août',
                'septembre',
                'octobre',
                'novembre',
                'décembre',
            ],
            $dateStr
        );
    }

    public static function french2English(string $date): string
    {
        return str_replace(
            [
                'janvier',
                'février',
                'mars',
                'avril',
                'mai',
                'juin',
                'juillet',
                'août',
                'septembre',
                'octobre',
                'novembre',
                'décembre',
            ],
            [
                'January',
                'February',
                'March',
                'April',
                'May',
                'June',
                'July',
                'August',
                'September',
                'October',
                'November',
                'December',
            ],
            $date
        );
    }
}
