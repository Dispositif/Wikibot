<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Utils;

class NumberUtil
{
    // tome et volume en romain
    const ROMAN_GLYPH
        = [
            1000 => 'M',
            900 => 'CM',
            500 => 'D',
            400 => 'CD',
            100 => 'C',
            90 => 'XC',
            50 => 'L',
            40 => 'XL',
            10 => 'X',
            9 => 'IX',
            5 => 'V',
            4 => 'IV',
            1 => 'I',
        ];

    /**
     * @param int       $number
     * @param bool|null $lowerSize
     *
     * @return string|null
     */
    public static function arab2roman(int $number, ?bool $lowerSize = false): ?string
    {
        if ($number <= 0) {
            return null;
        }
        $result = '';
        foreach (static::ROMAN_GLYPH as $limit => $glyph) {
            while ($number >= $limit) {
                $result .= $glyph;
                $number -= $limit;
            }
        }
        if ($lowerSize) {
            $result = strtolower($result);
        }

        return $result;
    }
}
