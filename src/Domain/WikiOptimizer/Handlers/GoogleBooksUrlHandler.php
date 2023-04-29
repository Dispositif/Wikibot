<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Domain\WikiOptimizer\Handlers;

use App\Domain\Models\Wiki\GoogleLivresTemplate;
use App\Domain\Publisher\GoogleBooksUtil;
use App\Domain\WikiOptimizer\OuvrageOptimize;
use DomainException;

/**
 * Normalize a Google Book links.
 * Clean the useless URL parameters or transform into wiki-template.
 */
class GoogleBooksUrlHandler extends AbstractOuvrageHandler
{
    protected $handlerParam = 'lire en ligne';

    public function sethandlerParam(string $handlerParam = 'lire en ligne'): self
    {
        $this->handlerParam = $handlerParam;
        return $this;
    }

    public function handle()
    {
        $url = $this->getParam($this->handlerParam);
        if (empty($url)
            || !GoogleBooksUtil::isGoogleBookURL($url)
        ) {
            return;
        }

        if (OuvrageOptimize::CONVERT_GOOGLEBOOK_TEMPLATE) {
            $template = GoogleLivresTemplate::createFromURL($url);
            if ($template instanceof GoogleLivresTemplate) {
                $this->setParam($this->handlerParam, $template->serialize());
                $this->addSummaryLog('{Google}');
                $this->optiStatus->setNotCosmetic(true);

                return;
            }
        }

        try {
            $goo = GoogleBooksUtil::simplifyGoogleUrl($url);
        } catch (DomainException $e) {
            // ID manquant ou malformé
            $errorValue = sprintf(
                '%s <!-- ERREUR %s -->',
                $url,
                $e->getMessage()
            );
            $this->setParam($this->handlerParam, $errorValue);
            $this->addSummaryLog('erreur URL');
            $this->optiStatus->setNotCosmetic(true);
            $this->optiStatus->setMajor(true);
        }

        if (!empty($goo) && $goo !== $url) {
            $this->setParam($this->handlerParam, $goo);
            // cleaned tracking parameters in Google URL ?
            if (GoogleBooksUtil::isTrackingUrl($url)) {
                $this->addSummaryLog('tracking');
                $this->optiStatus->setNotCosmetic(true);
            }
        }
    }
}