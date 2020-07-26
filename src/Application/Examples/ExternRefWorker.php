<?php

/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */
declare(strict_types=1);

namespace App\Application\Examples;

use App\Application\ExternRefTransformer;
use App\Application\RefBotWorker;
use App\Application\WikiBotConfig;
use App\Infrastructure\CirrusSearch;
use App\Infrastructure\Logger;
use App\Infrastructure\PageList;
use App\Infrastructure\ServiceFactory;
use Codedungeon\PHPCliColors\Color;
use Throwable;

//$env = 'test';
//include __DIR__.'/../ZiziBot_Bootstrap.php';
include __DIR__.'/../myBootstrap.php'; // Codex

// todo VOIR EN BAS

/**
 * TODO move PROCESS FROM BOTTOM
 * Class Ref2ArticleWorker
 *
 * @package App\Application\Examples
 */
class ExternRefWorker extends RefBotWorker
{
    const TASK_NAME                   = "🔗 Complètement de références : URL ⇒ modèle"; // 😎
    const TASK_BOT_FLAG               = false;
    const SLEEP_AFTER_EDITION         = 30; // sec
    const DELAY_AFTER_LAST_HUMAN_EDIT = 15; // minutes
    const CHECK_EDIT_CONFLICT         = true;

    protected $botFlag = false;
    protected $modeAuto = true;
    /**
     * @var ExternRefTransformer
     */
    protected $transformer;

    /**
     * ExternalRefWorker constructor.
     */
    protected function setUpInConstructor(): void
    {
        $transformer = new ExternRefTransformer($this->log);
        $transformer->skipUnauthorised = false;

        $this->transformer = $transformer;
        //todo? move in __constructor + parent::__constructor()
    }

    // todo private (refac constructor->run())

    /**
     * Traite contenu d'une <ref> ou bien lien externe (précédé d'une puce).
     *
     * @param $refContent
     *
     * @return string
     */
    public function processRefContent($refContent): string
    {
        // todo // hack Temporary Skip URL
        if (preg_match('#books\.google#', $refContent)) {
            return $refContent;
        }

        try {
            $result = $this->transformer->process($refContent);
        } catch (Throwable $e) {
            echo "** Problème détecté 234242\n";
            $this->log->critical($e->getMessage()." ".$e->getFile().":".$e->getLine());

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
            //            $ask = readline(Color::LIGHT_MAGENTA."*** Conserver cette modif ? [y/n]".Color::NORMAL);
            //            if ($ask !== 'y') {
            //                return $refContent;
            //            }
        }

        return $result;
    }

}

/** @noinspection PhpUnhandledExceptionInspection */
$wiki = ServiceFactory::wikiApi();
$logger = new Logger();
//$logger->debug = true;
$botConfig = new WikiBotConfig($logger);

// &srqiprofile=popular_inclinks_pv&srsort=last_edit_desc
$list = new CirrusSearch(
    'https://fr.wikipedia.org/w/api.php?action=query&list=search'
    //    .'&srsearch=%22https%3A%2F%2Fjournals.openedition.org%22+insource%3A%2F%5C%3Cref%5C%3Ehttps%5C%3A%5C%2F%5C%2Fjournals%5C.openedition%5C.org%5B%5E%5C%3E%5D%2B%5C%3C%5C%2Fref%3E%2F'
    .'&srsearch=%22https%3A%2F%2F%22+insource%3A%2F%5C%3Cref%5C%3Ehttps%5C%3A%5C%2F%5C%2F%5B%5E%5C%3E%5D%2B%5C%3C%5C%2Fref%3E%2F'
    .'&formatversion=2&format=json&srnamespace=0&srlimit=1000&srqiprofile=popular_inclinks_pv&srsort=last_edit_desc'
//srsort=random'
);

if (!empty($argv[1])) {
    $list = new PageList([trim($argv[1])]);
}

new ExternRefWorker($botConfig, $wiki, $list);
