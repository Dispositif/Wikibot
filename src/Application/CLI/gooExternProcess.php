<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

namespace App\Application\CLI;

use App\Application\GoogleBooksWorker;
use App\Application\WikiBotConfig;
use App\Infrastructure\CirrusSearch;
use App\Infrastructure\GoogleApiQuota;
use App\Infrastructure\Monitor\ConsoleLogger;
use App\Infrastructure\PageList;
use App\Infrastructure\ServiceFactory;
use Exception;

/**
 * Searches for raw Google Books links (<ref…> and bullet list * ) and transforms them into {ouvrage}
 * Consumes GoogleBooks API daily quota
 */

include __DIR__.'/../CodexBot2_Bootstrap.php';

$quota = new GoogleApiQuota();
echo 'Google quota: '.$quota->getCount();

if ($quota->isQuotaReached()) {
    throw new Exception("Google Books API quota reached => exit");
}

$wiki = ServiceFactory::getMediawikiFactory();
$logger = new ConsoleLogger();
//$logger->debug = true; // todo option
//$logger->verbose = true;
//$logger->colorMode = true;
$bot = new WikiBotConfig($wiki, $logger);
$bot->checkStopOnTalkpageOrException();
$bot->setTaskName("🌐📘 Amélioration bibliographique : lien Google Books ⇒ {ouvrage}");

// TODO : https://www.google.com/books/edition/A_Wrinkle_in_Time/r119-dYq0mwC
$list = new CirrusSearch(
    [
        'srsearch' => '"https://books.google" insource:/\<ref[^\>]*\> *https\:\/\/books\.google/',
//        'srsearch' => 'https://books.google" insource:/\* *https\:\/\/books\.google/', // liste à puces
        'srnamespace' => '0',
        'srlimit' => '500',
        'srqiprofile' => CirrusSearch::SRQIPROFILE_POPULAR_INCLINKS_PV,
    ],
    [CirrusSearch::OPTION_CONTINUE => true]
);
$titles = $list->getPageTitles();
echo 'CirrusSearch: '.count($titles).' titles found';
$list = new PageList($titles);

// --dry-run can appear anywhere; strip it before reading the positional page title.
$dryRun = in_array('--dry-run', $argv, true);
$positionalArgs = array_values(array_filter($argv, static fn ($a) => $a !== '--dry-run'));
if (!empty($positionalArgs[1])) {
    $list = new PageList([trim($positionalArgs[1])]);
}

new GoogleBooksWorker($bot, $wiki, $list, $dryRun);
// todo 2023 : desactivate "Skip : déjà analysé"
