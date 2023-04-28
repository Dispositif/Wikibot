<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Domain\Transformers\Handlers;

use App\Domain\Models\Wiki\GoogleLivresTemplate;
use Exception;

/**
 * Complétion lire/présentation en ligne, si vide.
 * Passe Google Book en accès partiel en 'lire en ligne' (sondage)
 * @throws Exception
 */
class MixLireEnLigne extends AbstractMixHandler
{
    public function handle()
    {
        // si déjà lire/présentation en ligne => on touche à rien
        if ($this->origin->hasParamValue('lire en ligne')
            || $this->origin->hasParamValue('présentation en ligne')
        ) {
            return;
        }

        // completion basique
        $booklire = $this->book->getParam('lire en ligne');
        if ($booklire) {
            $this->origin->setParam('lire en ligne', $booklire);
            $this->changeOptiStatus();

            return;
        }

        $presentation = $this->book->getParam('présentation en ligne') ?? false;
        // Ajout du partial Google => mis en lire en ligne
        // plutôt que 'présentation en ligne' selon sondage
        if (!empty($presentation) && GoogleLivresTemplate::isGoogleBookValue($presentation)) {
            $this->origin->setParam('lire en ligne', $presentation);
            $this->changeOptiStatus();
        }
    }

    protected function changeOptiStatus(): void
    {
        $this->optiStatus->addSummaryLog('+lire en ligne');
        $this->optiStatus->setMajor(true);
        $this->optiStatus->setNotCosmetic(true);
    }
}