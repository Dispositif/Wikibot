<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\WikiOptimizer\Handlers;

use App\Domain\Models\Wiki\LienWebTemplate;
use App\Domain\Utils\WikiTextUtil;
use Psr\Log\LoggerInterface;

class WebSitePeriodiqueHandler implements OptimizeHandlerInterface
{
    /**
     * @var LienWebTemplate
     */
    protected $template;
    /**
     * @var LoggerInterface
     */
    protected $log;

    public function __construct(LienWebTemplate $template, LoggerInterface $log)
    {
        $this->template = $template;
        $this->log = $log;
    }

    public function handle()
    {
        $this->siteNameInTitle();

        if (empty($this->template->getParam('périodique'))) {
            return;
        }
        // doublon site - périodique
        if ($this->template->getParam('site') === $this->template->getParam('périodique')) {
            $this->template->unsetParam('périodique');
            $this->log->info('doublon site/périodique');

            return;
        }

        //quasi doublon site - périodique
        $periodiqueWords = strtolower(str_replace(
            [' ', '-'],
            '',
            $this->template->getParam('périodique')
        ));
        $siteWords = strtolower(str_replace([' ', '-'], '', $this->template->getParam('site')));
        if (str_contains($siteWords, $periodiqueWords)) {
            $this->template->unsetParam('périodique');
            $this->log->info('quasi doublon site/périodique');
        }
    }

    /**
     * Legacy. Replaced by the clever Publisher/SeoSanitizer.
     */
    private function siteNameInTitle()
    {
        // "Mali - Vidéo Dailymotion"
        // "bla - PubMed"
        $siteName = WikiTextUtil::unWikify($this->template->getParam('site') ?? '');
        if (empty($siteName)) {
            return;
        }
        $newTitle = preg_replace(
            '#[- ]*(vidéo|site de|site|sur) ?' . $siteName . '$#i',
            '',
            $this->template->getParam('titre')
        );
        $this->template->setParam('titre', trim($newTitle));
    }
}