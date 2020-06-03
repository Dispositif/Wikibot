<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Utils\WikiTextUtil;

class LienWebOptimizer extends AbstractTemplateOptimizer
{

    public function doTasks()
    {
        $this->doublonSitePeriodique();
        $this->cleanAuthor();
        $this->siteNameInTitle();

        return $this;
    }

    private function doublonSitePeriodique()
    {
        // doublon site - périodique
        if (!empty($this->getParam('site'))
            && ($this->getParam('site') === $this->getParam('périodique'))
        ) {
            $this->unsetParam('site');
        }
    }

    private function cleanAuthor()
    {
        if ($this->getParam('auteur1') === 'Rédaction') {
            $this->unsetParam('auteur1');
        }
        // doublon auteur - site  ou doublon auteur - périodique
        if ((WikiTextUtil::unWikify($this->getParam('auteur1') ?? '') === WikiTextUtil::unWikify(
                    $this->getParam('site') ?? ''
                ))
            || (WikiTextUtil::unWikify($this->getParam('auteur1') ?? '') === WikiTextUtil::unWikify(
                    $this->getParam('périodique') ?? ''
                ))
        ) {
            $this->unsetParam('auteur1');
        }
    }

    private function siteNameInTitle()
    {
        // "Mali - Vidéo Dailymotion"
        // "bla - PubMed"
        $siteName = WikiTextUtil::unWikify($this->getParam('site') ?? '');
        $newTitle = preg_replace('#[- ]*(vidéo|site de|site|sur) ?'.$siteName.'$#i', '', $this->getParam('titre'));
        $this->setParam('titre', trim($newTitle));
    }
}
