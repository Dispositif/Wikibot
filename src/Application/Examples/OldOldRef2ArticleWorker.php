<?php

/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */
declare(strict_types=1);

namespace App\Application\Examples;

use App\Application\PublisherAction;
use App\Application\Ref2ArticleProcess;
use App\Application\RefBotWorker;
use App\Application\WikiBotConfig;
use App\Domain\Models\Wiki\ArticleOrLienBriseInterface;
use App\Domain\Models\Wiki\LienBriseTemplate;
use App\Domain\Publisher\ArticleFromURL;
use App\Infrastructure\CirrusSearch;
use App\Infrastructure\ServiceFactory;

include __DIR__.'/../myBootstrap.php';

/**
 * TODO move PROCESS FROM BOTTOM
 * Class Ref2ArticleWorker
 *
 * @package App\Application\Examples
 */
class OldRef2ArticleWorker extends RefBotWorker
{
    const TASK_NAME           = "Amélioration bibliographique : URL ⇒ {Article}"; // 😎
    const TASK_BOT_FLAG       = false;
    const SLEEP_AFTER_EDITION = 120;

    protected $botFlag = false;
    protected $modeAuto = false;

    public function processRefContent($refContent): string
    {
        $converter = new ArticleFromURL(new PublisherAction($refContent));
        $articleOrLienBrise = $converter->getResult();

        if (!$articleOrLienBrise instanceof ArticleOrLienBriseInterface) {
            return $refContent;
        }
        if ($articleOrLienBrise instanceof LienBriseTemplate) {
            $this->taskName = '⚠ Amélioration bibliographique : <ref> avec lien brisé !';
            $this->botFlagOnPage = false;
            $this->warning = true;
        }

        return $articleOrLienBrise->serialize(true);
    }

}

// &srqiprofile=popular_inclinks_pv&srsort=last_edit_desc
$cirrusURL
    = 'https://fr.wikipedia.org/w/api.php?action=query&list=search&srsearch=%22https%3A%2F%2Fwww.lemonde.fr%22+insource%3A%2F%5C%3Cref%5C%3Ehttps%5C%3A%5C%2F%5C%2Fwww%5C.lemonde%5C.fr%5B%5E%5C%3E%5D%2B%5C%3C%5C%2Fref%3E%2F&formatversion=2&format=json&srnamespace=0&srlimit=1000&srqiprofile=popular_inclinks_pv&srsort=random';
$wiki = ServiceFactory::wikiApi();
$botConfig = new WikiBotConfig();
$cirrusList = new CirrusSearch($cirrusURL);
new OldRef2ArticleWorker($botConfig, $wiki, $cirrusList);
