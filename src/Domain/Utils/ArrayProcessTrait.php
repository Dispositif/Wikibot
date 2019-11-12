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
     * Delete keys with empty string value "".
     *
     * @param array $myArray
     *
     * @return array
     */
    public function deleteEmptyValueArray(array $myArray): array
    {
        return array_filter($myArray, function ($value) {
            return !is_null($value) && '' !== $value;
        });
    }
}
