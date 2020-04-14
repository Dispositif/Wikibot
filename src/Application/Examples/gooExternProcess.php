<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

namespace App\Application\Examples;

use App\Application\GoogleBooksWorker;
use App\Application\WikiBotConfig;
use App\Infrastructure\CirrusSearch;
use App\Infrastructure\GoogleApiQuota;
use App\Infrastructure\ServiceFactory;


include __DIR__.'/../myBootstrap.php';

dump('Google quota : ', (new GoogleApiQuota())->getCount());

$wiki = ServiceFactory::wikiApi();
$bot = new WikiBotConfig();

// les "* https://..." en biblio et liens externes
// "https://books.google" insource:/\* https\:\/\/books\.google[^ ]+/

$cirrusURL
    = 'https://fr.wikipedia.org/w/api.php?action=query&list=search'.'&srsearch='.urlencode(
        '"https://books.google" insource:/\* https\:\/\/books\.google/'
    ).'&formatversion=2&format=json&srnamespace=0'.'&srlimit=100&srqiprofile=popular_inclinks_pv&srsort=last_edit_desc';
//            .'&srlimit=100&srsort=random';
$pageListGen = new CirrusSearch($cirrusURL);


new GoogleBooksWorker($bot, $wiki, $pageListGen);
