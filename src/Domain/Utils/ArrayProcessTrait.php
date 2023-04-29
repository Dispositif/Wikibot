<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Utils;

trait ArrayProcessTrait
{
    /**
     * Set all array keys to lower case (do not change the values).
     */
    private static function arrayKeysToLower(array $array): array
    {
        $res = [];
        foreach ($array as $key => $val) {
            $res[mb_strtolower($key)] = $val;
        }

        return $res;
    }

    public function deleteEmptyValueArray(array $array): array
    {
        return array_filter($array, fn($value) => null !== $value && '' !== $value);
    }
}
