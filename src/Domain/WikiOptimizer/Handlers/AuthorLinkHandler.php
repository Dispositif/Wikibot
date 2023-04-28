<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\WikiOptimizer\Handlers;

use App\Domain\Utils\WikiTextUtil;

class AuthorLinkHandler extends AbstractOuvrageHandler
{
    public function handle()
    {
        $auteurParams = ['auteur1', 'auteur2', 'auteur2', 'titre'];
        foreach ($auteurParams as $auteurParam) {
            if ($this->ouvrage->hasParamValue($auteurParam)
                && $this->ouvrage->hasParamValue('lien '.$auteurParam)
            ) {
                $this->ouvrage->setParam(
                    $auteurParam,
                    WikiTextUtil::wikilink(
                        $this->ouvrage->getParam($auteurParam),
                        $this->ouvrage->getParam('lien '.$auteurParam)
                    )
                );
                $this->ouvrage->unsetParam('lien '.$auteurParam);
                $this->optiStatus->addSummaryLog('±lien '.$auteurParam);
                $this->optiStatus->setNotCosmetic(true);
            }
        }
    }
}