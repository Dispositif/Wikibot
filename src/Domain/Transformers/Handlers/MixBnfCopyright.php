<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Domain\Transformers\Handlers;


class MixBnfCopyright extends AbstractMixHandler
{
    public function handle()
    {
        if ($this->optiStatus->isNotCosmetic() && 'BnF' === $this->book->getDataSource()) {
            $this->optiStatus->addSummaryLog('@BnF');
        }
    }
}