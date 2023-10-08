<?php

/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */
declare(strict_types=1);

namespace App\Application\CLI;

use App\Application\ExternLink\ExternRefWorker;
use App\Application\WikiBotConfig;
use App\Infrastructure\CirrusSearch;
use App\Infrastructure\ConsoleLogger;
use App\Infrastructure\InternetDomainParser;
use App\Infrastructure\PageList;
use App\Infrastructure\ServiceFactory;

/**
 * Traitement synchrone des URL brutes http:// transformée en {lien web} ou {article}
 */

//$env = 'test';
include __DIR__.'/../ZiziBot_Bootstrap.php';
//include __DIR__.'/../myBootstrap.php'; // Codex

// todo VOIR EN BAS

/** @noinspection PhpUnhandledExceptionInspection */
$wiki = ServiceFactory::getMediawikiFactory();
$logger = new ConsoleLogger();
$logger->colorMode = true;
//$logger->debug = true;
$botConfig = new WikiBotConfig($wiki, $logger);
$botConfig->taskName = "🐭 Amélioration de références : URL ⇒ "; // 🐞 🌐  🔗

$botConfig->checkStopOnTalkpageOrException();

// LAST EDIT
// TODO : \<ref[^\>]*\> et liste à puces * http://...
// todo 1600 avec espace entre <ref> et http : "http" insource:/\<ref[^\>]*\> +https?\:\/\/[^\>]+\<\/ref>/
$list = new CirrusSearch(
    [
        'srsearch' => '"http" insource:/\<ref[^\>]*\> ?https?\:\/\/[^\<\ ]+ *\<\/ref/',
        'srlimit' => '5000',
        'srqiprofile' => 'popular_inclinks_pv',
        'srsort' => 'last_edit_desc',
    ]
);
$list->setOptions(['reverse' => true]);


// filter titles already in edited.txt
$titles = $list->getPageTitles();
unset($list);
//echo count($titles)." titles\n";
$edited = file(__DIR__.'/../resources/article_externRef_edited.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$filtered = array_diff($titles, $edited);
$list = new PageList( $filtered );
echo ">".$list->count()." dans liste\n";

new ExternRefWorker($botConfig, $wiki, $list, null, new InternetDomainParser());

echo "END of process\n";