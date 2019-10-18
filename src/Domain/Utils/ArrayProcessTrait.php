<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 (c) Philippe M. <dispositif@gmail.com>
 * For the full copyright and license information, please view the LICENSE file
 */

declare(strict_types=1);

namespace App\Domain\Utils;

trait ArrayProcessTrait
{
    /**
     * Delete keys with empty string value "".
     *
     * @param array $myArray
     *
     * @return array
     */
    public function deleteEmptyValueArray(array $myArray)
    {
        $result = [];
        foreach ($myArray as $key => $value) {
            if (!empty($key) && !empty($value)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
