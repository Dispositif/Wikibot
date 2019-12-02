<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Models\Wiki;

use App\Domain\Utils\ArrayProcessTrait;

abstract class AbstractParametersObject
{
    use ArrayProcessTrait;

    protected $parametersValues;

    /**
     * todo extract.
     */
    public function generateSetterMethodList()
    {
        $list = '';
        foreach ($this->parametersByOrder as $name) {
            $method = $this->setterMethodName($name);
            $list .= 'private function '.$method."() { }\n";
        }
        echo "<pre>$list</pre>";
    }

    /**
     * Magic param setter.
     *
     * @param $name
     *
     * @return string
     */
    protected function setterMethodName(string $name): string
    {
        $sanitizedName = str_replace([' ', 'à', 'é'], ['', 'a', 'e'], $name);
        $sanitizedName = preg_replace('#[^A-Za-z0-9]#', '', $sanitizedName);

        return 'set'.ucfirst($sanitizedName);
    }

    /**
     * todo clean empty value ??
     *
     * @return array
     */
    final public function toArray(): array
    {
        return $this->deleteEmptyValueArray($this->parametersValues);
    }

    /**
     * Compare param/value datas. Empty value ignored. Params order ignored.
     *
     * @param AbstractParametersObject $object
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
