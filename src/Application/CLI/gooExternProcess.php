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


/**
 * Cherche des liens bruts Google Books (<ref> et liste * ) et les transforme en {ouvrage}
 * Consomme du quota journalier GB
 */

include __DIR__.'/../myBootstrap.php';

$quota = new GoogleApiQuota();
dump('Google quota : '.$quota->getCount());

if ($quota->isQuotaReached()) {
    throw new \Exception("Google Books API quota reached => exit");
}


$wiki = ServiceFactory::getMediawikiFactory();
$bot = new WikiBotConfig($wiki, new ConsoleLogger());
$bot->setTaskName("🌐📘 Amélioration bibliographique : lien Google Books ⇒ {ouvrage}");

// les "* https://..." en biblio et liens externes
// "https://books.google" insource:/\* https\:\/\/books\.google[^ ]+/
$list = new CirrusSearch(
    [
        //        'srsearch' => '"https://books.google" insource:/\* ?https\:\/\/books\.google/', // liste à puce
        //        'srsearch' => '"https://books.google" insource:/\<ref[^\>]*\> *https\:\/\/books\.google/[^\ <]+ *<\/ref/',
        //        'srsearch' => '"https://books.google" insource:/\<ref[^\>]*\> ?https\:\/\/books\.google[^\ <]+ *[^<]+\<\/ref/',
        'srsearch' => '"https://books.google" insource:/\<ref[^\>]*\> *https\:\/\/books\.google/',
//        'srsearch' => 'https://books.google" insource:/\* *https\:\/\/books\.google/', // liste à puces
        'srnamespace' => '0',
        'srlimit' => '1000',
        //        'srqiprofile' => 'popular_inclinks_pv',
        'srsort' => 'last_edit_desc',
    ]
);
// TODO : https://www.google.com/books/edition/A_Wrinkle_in_Time/r119-dYq0mwC

if (!empty($argv[1])) {
    $list = new PageList([trim($argv[1])]);
}

new GoogleBooksWorker($bot, $wiki, $list);
// todo desactivate "Skip : déjà analysé"
