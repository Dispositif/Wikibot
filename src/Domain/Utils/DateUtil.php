<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe/Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Utils;

use DateTime;
use DateTimeZone;
use Exception;

/**
 * see TYPO https://fr.wikipedia.org/wiki/Wikip%C3%A9dia:AutoWikiBrowser/Typos#Dates
 * Class DateUtil.
 */
class DateUtil
{
    /**
     * todo
     *
     *
     * @return DateTime
     * @throws Exception
     */
    public static function fromWikiSignature(string $string)
    {
        // 1 janvier 2020 à 17:44 (CET) => 1 January 2020 à 17:44 (CET)
        $string = self::french2English(trim($string));

        $timezone = new DateTimeZone('Europe/Paris'); // CET, CEST
        if (preg_match('/\(UTC\)/', (string) $string)) {
            $timezone = new DateTimeZone('UTC');
        }
        $string = preg_replace('/\([A-Z]{3,4}\)/', '', (string) $string); // strip CET,CEST,UTC
        // convert fuseau ? https://stackoverflow.com/questions/5746531/utc-date-time-string-to-timezone

        return DateTime::createFromFormat('d M Y \à H\:i', trim($string), $timezone);
    }

    public function cleanDate(string $date)
    {
        return static::english2french($date);
    }

    public static function english2french(string $date)
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
            $date
        );
    }

    public static function french2English(string $date)
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
