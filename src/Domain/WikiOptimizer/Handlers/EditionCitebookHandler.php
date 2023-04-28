<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\WikiOptimizer\Handlers;

/**
 * {Cite book}:"edition" [ordinal number] => {ouvrage}::"numéro d'édition" (ou "réimpression" [année])
 * {Cite book}:origyear => {ouvrage}:"année première édition"
 * https://wstat.fr/template/index.php?title=Ouvrage&query=paramvalue&param=edition&limit=5000&searchtext=.&searchre=1
 * Pas mal de corrupted sur "éditions"
 * https://wstat.fr/template/index.php?title=Ouvrage&query=paramvalue&param=%C3%A9dition&limit=5000&searchtext=.&searchre=1
 * Note : impossible de faire getParam("éditeur-doublon")
 */
class EditionCitebookHandler extends AbstractOuvrageHandler
{
    public function handle()
    {
        // "édition" alias de "éditeur", mais OuvrageTemplateAlias:"édition"=>"numéro d'édition" à cause des doublons
        if (!empty($this->ouvrage->getParam("numéro d'édition"))) {
            $numeroEdition = $this->ouvrage->getParam("numéro d'édition");
            if (empty($this->ouvrage->getParam('éditeur'))
                && $this->getEditionOrdinalNumber($numeroEdition) === null
                && !$this->isEditionYear($numeroEdition)
            ) {
                $this->ouvrage->setParam('éditeur', $numeroEdition);
                $this->ouvrage->unsetParam("numéro d'édition");
                $this->optiStatus->addSummaryLog('±éditeur');
            }
        }

        // Correction nom de paramètre selon type de valeur
        $this->correctReimpressionByParam("numéro d'édition");
        $this->correctReimpressionByParam("éditeur");
        $this->correctReimpressionByParam("édition");
    }

    private function getEditionOrdinalNumber(?string $str): ?string
    {
        if (!$str) {
            return null;
        }
        // {{5e}}
        if (preg_match('#^\{\{(\d+)e\}\}$#', $str, $matches)) {
            return $matches[1];
        }
        // "1st ed."
        if (preg_match(
            '#^(\d+) ?(st|nd|rd|th|e|ème)? ?(ed|ed\.|edition|reprint|published|publication)?$#i',
            $str,
            $matches
        )
        ) {
            return $matches[1];
        }

        return null;
    }

    private function isEditionYear(string $str): bool
    {
        return preg_match('#^\d{4}$#', $str) && (int)$str > 1700 && (int)$str < 2025;
    }

    private function correctReimpressionByParam(string $param): void
    {
        $editionNumber = $this->ouvrage->getParam($param);
        if (!empty($editionNumber) && $this->isEditionYear($editionNumber)) {
            $this->ouvrage->unsetParam($param);
            $this->ouvrage->setParam('réimpression', $editionNumber);
            $this->optiStatus->addSummaryLog('+réimpression');
            $this->optiStatus->setNotCosmetic(true);

            return;
        }

        $editionOrdinal = $this->getEditionOrdinalNumber($editionNumber);
        if (!empty($editionNumber) && !$this->isEditionYear($editionNumber) && $editionOrdinal) {
            $this->ouvrage->unsetParam($param);
            $this->ouvrage->setParam("numéro d'édition", $editionOrdinal);
            $this->optiStatus->addSummaryLog("±numéro d'édition");
            $this->optiStatus->setNotCosmetic(true);
        }
    }
}