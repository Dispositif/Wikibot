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
$botConfig->taskName = "🔗 Complètement de références : URL ⇒ modèle"; // 😎🐞

// LAST EDIT
// &srqiprofile=popular_inclinks_pv&srsort=last_edit_desc
$list = new CirrusSearch(
    'https://fr.wikipedia.org/w/api.php?action=query&list=search'
    .'&srsearch=%22https%3A%2F%2F%22+insource%3A%2F%5C%3Cref%5C%3Ehttps%5C%3A%5C%2F%5C%2F%5B%5E%5C%3E%5D%2B%5C%3C%5C%2Fref%3E%2F'
    .'&formatversion=2&format=json&srnamespace=0&srlimit=10000&srqiprofile=popular_inclinks_pv&srsort=last_edit_desc'
);
//$list->setOptions(['reverse' => true]);


//// RANDOM :
//$list = new CirrusSearch(
//    'https://fr.wikipedia.org/w/api.php?action=query&list=search'
//    .'&srsearch=%22https%3A%2F%2F%22+insource%3A%2F%5C%3Cref%5C%3Ehttps%5C%3A%5C%2F%5C%2F%5B%5E%5C%3E%5D%2B%5C%3C%5C%2Fref%3E%2F'
//    .'&formatversion=2&format=json&srnamespace=0&srlimit=10000&srsort=random'
//);

if (!empty($argv[1])) {
    $list = new PageList([trim($argv[1])]);
    $botConfig->taskName = '🐞'.$botConfig->taskName;
}

new ExternRefWorker($botConfig, $wiki, $list);
