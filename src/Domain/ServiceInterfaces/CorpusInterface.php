<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ServiceInterfaces;

interface CorpusInterface
{
    public function inCorpus(string $element, string $corpusName): bool;

    public function addNewElementToCorpus(string $corpusName, string $element): bool;
}
