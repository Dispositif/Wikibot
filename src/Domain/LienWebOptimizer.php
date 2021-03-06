<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe/Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
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

    private function doublonSitePeriodique(): void
    {
        if (empty($this->getParam('périodique'))) {
            return;
        }
        // doublon site - périodique
        if ($this->getParam('site') === $this->getParam('périodique')) {
            $this->unsetParam('périodique');
            $this->log->info('doublon site/périodique');

            return;
        }

        //quasi doublon site - périodique
        $periodiqueWords = strtolower(str_replace([' ', '-'], '', $this->getParam('périodique')));
        $siteWords = strtolower(str_replace([' ', '-'], '', $this->getParam('site')));
        if (strpos($siteWords, $periodiqueWords) !== false) {
            $this->unsetParam('périodique');
            $this->log->info('quasi doublon site/périodique');
        }
    }

    private function cleanAuthor()
    {
        if (in_array($this->getParam('auteur1'), ['Rédaction', 'La Rédaction'])) {
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

    /**
     * Supprime nom du site présent dans le titre (doublon)
     *
     * @throws \Exception
     * @todo testing
     */
    private function siteNameInTitle()
    {
        // "Mali - Vidéo Dailymotion"
        // "bla - PubMed"
        $siteName = WikiTextUtil::unWikify($this->getParam('site') ?? '');
        if (empty($siteName)) {
            return;
        }
        $newTitle = preg_replace('#[- ]*(vidéo|site de|site|sur) ?'.$siteName.'$#i', '', $this->getParam('titre'));
        $this->setParam('titre', trim($newTitle));
    }
}
