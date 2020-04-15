<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);


namespace App\Domain\Models\Wiki;


interface WikiTemplateInterface
{
    public function isValidForEdit(): bool;

    public function getParam(string $name): ?string;

    public function setParam(string $name, string $value): AbstractParametersObject;

    public function unsetParam(string $name);

    public function hydrate(array $data): AbstractStrictWikiTemplate;

    //    public function hydrateFromText(string $tplText): AbstractWikiTemplate;

    public function hasParamValue(string $name): bool;

    public function toArray(): array;

    public function serialize(?bool $cleanOrder = false): string;
}
