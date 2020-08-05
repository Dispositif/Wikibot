<?php

/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */
declare(strict_types=1);

namespace App\Application\Examples;

use App\Application\ExternRefWorker;
use App\Application\WikiBotConfig;
use App\Infrastructure\CirrusSearch;
use App\Infrastructure\Logger;
use App\Infrastructure\PageList;
use App\Infrastructure\ServiceFactory;

/**
 * Traitement synchrone des URL brutes http:// transformée en {lien web} ou {article}
 */

//$env = 'test';
//include __DIR__.'/../ZiziBot_Bootstrap.php';
include __DIR__.'/../myBootstrap.php'; // Codex

// todo VOIR EN BAS

/** @noinspection PhpUnhandledExceptionInspection */
$wiki = ServiceFactory::wikiApi();
$logger = new Logger();
//$logger->debug = true;
$botConfig = new WikiBotConfig($logger);
$botConfig->taskName = "🔗 Complètement de références : URL ⇒ "; // 😎🐞

// LAST EDIT
// TODO : \<ref[^\>]*\> et liste à puces * http://...
// todo 1600 avec espace entre <ref> et http : "http" insource:/\<ref[^\>]*\> +https?\:\/\/[^\>]+\<\/ref>/
$list = new CirrusSearch(
    [
        'srsearch' => '"http" insource:/\<ref\>https?\:\/\/[^\>]+\<\/ref>/',
        'srlimit' => '500',
        'srqiprofile' => 'popular_inclinks_pv',
        'srsort' => 'last_edit_desc',
    ]
);
$list->setOptions(['reverse' => true]);


//// RANDOM :
//$list = new CirrusSearch(
//    [
//        'srsearch' => '"http" insource:/\<ref\>https?\:\/\/[^\>]+\<\/ref>/',
//        'srnamespace' => '0',
//        'srlimit' => '100',
//        'srsort' => 'random',
//    ]
//);

if (!empty($argv[1])) {
    $list = new PageList([trim($argv[1])]);
    $botConfig->taskName = '🐞'.$botConfig->taskName;
}

new ExternRefWorker($botConfig, $wiki, $list);
