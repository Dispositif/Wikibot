<?php

/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */
declare(strict_types=1);

namespace App\Application\Examples;

use App\Application\Color;
use App\Application\Ref2ArticleProcess;
use App\Application\RefBotWorker;
use App\Application\RefWebTransformer;
use App\Application\WikiBotConfig;
use App\Domain\Publisher\ArticleFromURL;
use App\Infrastructure\CirrusSearch;
use App\Infrastructure\ServiceFactory;

include __DIR__.'/../ZiziBot_Bootstrap.php';
// VOIR EN BAS

/**
 * TODO move PROCESS FROM BOTTOM
 * Class Ref2ArticleWorker
 *
 * @package App\Application\Examples
 */
class RefWebWorker extends RefBotWorker
{
    const TASK_NAME           = "Amélioration références : URL ⇒ {modèle}"; // 😎
    const TASK_BOT_FLAG       = false;
    const SLEEP_AFTER_EDITION = 30;

    protected $botFlag = false;
    protected $modeAuto = false;
    /**
     * @var RefWebTransformer
     */
    protected $transformer;

    public function processRefContent($refContent): string
    {
        try {
            $result = $this->transformer->process($refContent);
        } catch (\Throwable $e) {
            echo "** Problème détecté\n";

            // TODO : parse $e->message pour traitement, taskName, botflag...
            return $refContent;
        }

        if ($result === $refContent) {
            return $refContent;
        }

        // Gestion semi-auto
        if (!$this->transformer->skipUnauthorised) {
            echo Color::BG_LIGHT_RED."--".Color::NORMAL." ".$refContent."\n";
            echo Color::BG_LIGHT_GREEN."++".Color::NORMAL." $result \n\n";
            $ask = readline(Color::LIGHT_MAGENTA."*** Conserver cette modif ? [y/n]".Color::NORMAL);
            if ($ask !== 'y') {
                return $refContent;
            }
        }

        return $result;
    }

    /**
     * RefWebWorker constructor.
     */
    protected function setUpInConstructor(): void
    {
        $transformer = new RefWebTransformer($this->log);
        $transformer->skipUnauthorised = false;

        $this->transformer = $transformer;
    }

}

$wiki = ServiceFactory::wikiApi();
$botConfig = new WikiBotConfig();

// &srqiprofile=popular_inclinks_pv&srsort=last_edit_desc
$list = new CirrusSearch(
    'https://fr.wikipedia.org/w/api.php?action=query&list=search'
    //    .'&srsearch=%22https%3A%2F%2Fjournals.openedition.org%22+insource%3A%2F%5C%3Cref%5C%3Ehttps%5C%3A%5C%2F%5C%2Fjournals%5C.openedition%5C.org%5B%5E%5C%3E%5D%2B%5C%3C%5C%2Fref%3E%2F'
    .'&srsearch=%22https%3A%2F%2F%22+insource%3A%2F%5C%3Cref%5C%3Ehttps%5C%3A%5C%2F%5C%2F%5B%5E%5C%3E%5D%2B%5C%3C%5C%2Fref%3E%2F'
    .'&formatversion=2&format=json&srnamespace=0&srlimit=100&srqiprofile=popular_inclinks_pv&srsort=random'
);


//$list = new PageList(['Pierre Zind', 'Économie du Soudan du Sud']);

new RefWebWorker($botConfig, $wiki, $list);
