<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Domain\WikiOptimizer\Handlers;

use Exception;

class ExternalTemplateHandler extends AbstractOuvrageHandler
{
    /**
     * TODO move+refac
     * TODO CommentaireBiblioTemplate  ExtraitTemplate
     * Probleme {{commentaire biblio}} <> {{commentaire biblio SRL}}
     * Generate supplementary templates from obsoletes params.
     * @throws Exception
     */
    public function handle()
    {
        // "extrait=bla" => {{citation bloc|bla}}
        if ($this->hasParamValue('extrait')) {
            $extrait = $this->getParam('extrait');
            // todo bug {{citation bloc}} si "=" ou "|" dans texte de citation
            // Legacy : use {{début citation}} ... {{fin citation}}
            if (preg_match('#[=|]#', $extrait) > 0) {
                $this->ouvrage->externalTemplates[] = (object)[
                    'template' => 'début citation',
                    '1' => '',
                    'raw' => '{{Début citation}}' . $extrait . '{{Fin citation}}',
                ];
                $this->addSummaryLog('{Début citation}');
                $this->optiStatus->setNotCosmetic(true);
            } else {
                // StdClass
                $this->ouvrage->externalTemplates[] = (object)[
                    'template' => 'citation bloc',
                    '1' => $extrait,
                    'raw' => '{{Citation bloc|' . $extrait . '}}',
                ];
                $this->addSummaryLog('{Citation bloc}');
                $this->optiStatus->setNotCosmetic(true);
            }

            $this->unsetParam('extrait');
            $this->optiStatus->setNotCosmetic(true);
        }

        // "commentaire=bla" => {{Commentaire biblio|1=bla}}
        if ($this->hasParamValue('commentaire')) {
            $commentaire = $this->getParam('commentaire');
            $this->ouvrage->externalTemplates[] = (object)[
                'template' => 'commentaire biblio',
                '1' => $commentaire,
                'raw' => '{{Commentaire biblio|' . $commentaire . '}}',
            ];
            $this->unsetParam('commentaire');
            $this->addSummaryLog('{commentaire}');
            $this->optiStatus->setNotCosmetic(true);
        }
    }
}