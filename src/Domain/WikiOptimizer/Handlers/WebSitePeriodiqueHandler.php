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
        $this->unsetDoublon('périodique');
        $this->unsetDoublon('éditeur');
    }

    /**
     * 'site' is the field kept ; the other one is dropped when it merely restates the
     * same publisher/site under a different param name (exact match, or one containing
     * the other once spacing/dashes/case are stripped).
     */
    private function unsetDoublon(string $otherParam): void
    {
        if (empty($this->template->getParam($otherParam))) {
            return;
        }

        if ($this->template->getParam('site') === $this->template->getParam($otherParam)) {
            $this->template->unsetParam($otherParam);
            $this->log->info("doublon site/$otherParam");

            return;
        }

        $otherWords = strtolower(str_replace([' ', '-'], '', $this->template->getParam($otherParam)));
        $siteWords = strtolower(str_replace([' ', '-'], '', $this->template->getParam('site')));
        if (str_contains($siteWords, $otherWords)) {
            $this->template->unsetParam($otherParam);
            $this->log->info("quasi doublon site/$otherParam");
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
            '#[- ]*(vidéo|site de|site|sur) ?' . preg_quote($siteName, '#') . '$#i',
            '',
            $this->template->getParam('titre')
        );
        $this->template->setParam('titre', trim($newTitle));
    }
}