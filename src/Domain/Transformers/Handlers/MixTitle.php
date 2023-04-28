<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Transformers\Handlers;

use App\Domain\Transformers\OuvrageMixTrait;
use App\Domain\Utils\WikiTextUtil;

class MixTitle extends AbstractMixHandler
{
    use OuvrageMixTrait;

    /**
     * Add or extract subtitle like in second book.
     */
    public function handle()
    {
        if (!$this->book->hasParamValue('sous-titre')
            || !$this->origin->hasParamValue('titre')
            || !$this->book->hasParamValue('titre')
        ) {
            return;
        }

        // Skip pour éviter conflit entre 'sous-titre' et 'collection' ou 'titre volume'
        if ($this->origin->hasParamValue('titre volume')
            || $this->origin->hasParamValue('titre chapitre')
            || $this->origin->hasParamValue('titre tome')
            || $this->origin->hasParamValue('collection')
            || $this->origin->hasParamValue('nature ouvrage')
        ) {
            return;
        }

        // simple : titres identiques mais sous-titre manquant
        // même titre mais sous-titre manquant
        if (
            $this->stripAll($this->origin->getParam('titre'))
            === $this->stripAll($this->book->getParam('titre'))
            && !$this->origin->hasParamValue('sous-titre')
        ) {
            $this->origin->setParam('sous-titre', $this->book->getParam('sous-titre'));
            $this->optiStatus->addSummaryLog('++sous-titre');
            $this->optiStatus->setMajor(true);
            $this->optiStatus->setNotCosmetic(true);

            return;
        }

        // compliqué : sous-titre inclus dans titre original => on copie titre/sous-titre de book
        // Exclusion wikification "titre=[[Fu : Bar]]" pour éviter => "titre=Fu|sous-titre=Bar"
        if ($this->charsFromBigTitle($this->origin) === $this->charsFromBigTitle($this->book)
            && !WikiTextUtil::isWikify($this->origin->getParam('titre') ?? '') && !$this->origin->hasParamValue('sous-titre')) {
            $this->origin->setParam('titre', $this->book->getParam('titre'));
            $this->origin->setParam('sous-titre', $this->book->getParam('sous-titre'));
            $this->optiStatus->addSummaryLog('>sous-titre');
        }
    }
}