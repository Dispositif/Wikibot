<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);


namespace App\Domain\Models\Wiki;

/**
 * Allows storage of hidden information or storage of parameter values
 * not serialized in a wiki-template.
 * Trait InfoTrait
 */
trait InfoTrait
{
    protected $infos = [];

    public function getInfos(): array
    {
        return $this->infos;
    }

    public function setInfos(array $infos): void
    {
        $this->infos = $infos;
    }

    public function getInfo(string $name): ?string
    {
        return ($this->infos[$name]) ?? null;
    }

    public function setInfo(string $name, string $value): void
    {
        $value = trim($value);
        if (!empty($value) || $this->infos[$name]) {
            $this->infos[$name] = $value;
        }
    }
}
