<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Domain\WikiOptimizer\Handlers;

use App\Domain\Predict\PredictAuthors;
use App\Domain\Utils\WikiTextUtil;
use Exception;

class DistinguishAuthorsHandler extends AbstractOuvrageHandler
{
    /**
     * Detect and correct multiple authors in same parameter.
     * Like "auteurs=J. M. Waller, M. Bigger, R. J. Hillocks".
     *
     *
     * @throws Exception
     */
    public function handle()
    {
        // merge params of author 1
        $auteur1 = $this->ouvrage->getParam('auteur') ?? '';
        $auteur1 .= $this->ouvrage->getParam('auteurs') ?? '';
        $auteur1 .= $this->ouvrage->getParam('prénom1') ?? '';
        $auteur1 .= ' ' . $this->ouvrage->getParam('nom1') ?? '';
        $auteur1 = trim($auteur1);
        // of authors 2
        $auteur2 = $this->ouvrage->getParam('auteur2') ?? '';
        $auteur2 .= $this->ouvrage->getParam('prénom2') ?? '';
        $auteur2 .= ' ' . $this->ouvrage->getParam('nom2') ?? '';
        $auteur2 = trim($auteur2);

        // skip if wikilink in author
        if (empty($auteur1) || WikiTextUtil::isWikify($auteur1)) {
            return;
        }

        $machine = new PredictAuthors();
        $res = $machine->predictAuthorNames($auteur1);

        if (1 === count((array)$res)) {
            // auteurs->auteur?
            return;
        }
        // Many authors... and empty "auteur2"
        if (count((array)$res) >= 2 && empty($auteur2)) {
            // delete author-params
            array_map(
                function ($param) {
                    $this->ouvrage->unsetParam($param);
                },
                ['auteur', 'auteurs', 'prénom1', 'nom1']
            );
            // iterate and edit new values
            $count = count((array)$res);
            for ($i = 0; $i < $count; ++$i) {
                $this->ouvrage->setParam(sprintf('auteur%s', $i + 1), $res[$i]);
            }
            $this->optiStatus->addSummaryLog('distinction auteurs');
            $this->optiStatus->setMajor(true);
            $this->optiStatus->setNotCosmetic(true);
        }
    }
}