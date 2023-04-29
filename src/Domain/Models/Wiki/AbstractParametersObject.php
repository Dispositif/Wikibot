<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Models\Wiki;

use App\Domain\Utils\ArrayProcessTrait;

/**
 * @notused
 */
abstract class AbstractParametersObject
{
    use ArrayProcessTrait;

    protected $parametersValues;

    /**
     * Magic param setter.
     */
    protected function setterMethodName(string $name): string
    {
        $sanitizedName = str_replace([' ', 'à', 'é'], ['', 'a', 'e'], $name);
        $sanitizedName = preg_replace('#[^A-Za-z0-9]#', '', $sanitizedName);

        return 'set'.ucfirst($sanitizedName);
    }

    /**
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->deleteEmptyValueArray($this->parametersValues);
    }

    /**
     * Compare param/value data. Empty value ignored. Params order ignored.
     *
     *
     * @return bool
     */
    public function isParamValueEquals(self $object): bool
    {
        $dat1 = $this->deleteEmptyValueArray($this->toArray());
        $dat2 = $this->deleteEmptyValueArray($object->toArray());
        if (count($dat1) !== count($dat2)) {
            return false;
        }
        foreach ($dat1 as $param => $value) {
            if (!isset($dat2[$param]) || $dat2[$param] !== $value) {
                return false;
            }
        }

        return true;
    }
}
