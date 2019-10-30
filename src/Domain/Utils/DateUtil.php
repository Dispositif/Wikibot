<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Utils;

/**
 * see TYPO https://fr.wikipedia.org/wiki/Wikip%C3%A9dia:AutoWikiBrowser/Typos#Dates
 * Class DateUtil
 */
class DateUtil
{
    public function cleanDate(string $date)
    {
        return $this->dateEnglish2french($date);
    }

    public function dateEnglish2french(string $date)
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
}
