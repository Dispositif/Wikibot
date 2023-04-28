<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Transformers\Handlers;

use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\Utils\WikiTextUtil;
use Exception;

/**
 * Complétion 'lien auteur1' d'après Wikidata et BnF.
 * Logique : faut pas confondre auteur1/auteur2 pour le lien auteur1.
 * @throws Exception
 */
class MixLienAuteur extends AbstractMixHandler
{
    public function handle()
    {
        $lienAuteur1 = $this->book->getParam('lien auteur1');
        if (empty($lienAuteur1)) {
            return;
        }
        if ($this->origin->hasParamValue('lien auteur1')) {
            $this->log->debug("lien auteur1 existe déjà\n");

            return;
        }

        $originAuteur1 = $this->concatParamsAuteur1($this->origin);
        $bookAuteur1 = $this->concatParamsAuteur1($this->book);

        // Check if wikilink in any of the author param
        if (WikiTextUtil::isWikify($originAuteur1)) {
            $this->log->debug("lien auteur1 existe déjà\n");

            return;
        }

        // WP:"Paul Durand" — Bnf "Paul Durand,..."
        if (!empty($bookAuteur1) && !empty($originAuteur1)
            && (mb_strtolower($bookAuteur1) === mb_strtolower($originAuteur1)
                || strpos($originAuteur1, $this->book->getParam('nom1') ?? '') !== false)
        ) {
            $this->origin->setParam('lien auteur1', $lienAuteur1);
            $this->optiStatus->addSummaryLog('+lien auteur1');
            $this->optiStatus->setNotCosmetic(true);
            $this->optiStatus->setMajor(true);
        } else {
            $this->log->debug('auteur1 pas identifié\n');
        }
        // todo: gérer "not same book" avec inversion auteur1/2 avant d'implémenter +lien auteur2
    }

    /**
     * Concaténation auteur/prénom/nom pour comparaison de wiki-modèles.
     */
    private function concatParamsAuteur1(OuvrageTemplate $ouvrage, ?int $num = 1): ?string
    {
        $auteur = $ouvrage->getParam('auteur' . $num) ?? '';
        $prenom = $ouvrage->getParam('prénom' . $num) ?? '';
        $nom = $ouvrage->getParam('nom' . $num) ?? '';

        return trim($auteur . ' ' . $prenom . ' ' . $nom);
    }
}