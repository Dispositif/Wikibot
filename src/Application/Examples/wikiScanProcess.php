<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application\Examples;

use App\Application\ScanWiki2DB;
use App\Application\WikiBotConfig;
use App\Infrastructure\CirrusSearch;
use App\Infrastructure\DbAdapter;
use App\Infrastructure\PageList;
use App\Infrastructure\ServiceFactory;


include __DIR__.'/../myBootstrap.php';

/**
 * From a titles list, scan the wiki and add the {ouvrage} citations into the DB
 */

$wiki = ServiceFactory::wikiApi();

// 100 dernier articles édités contenant un {ouvrage}
$list = new CirrusSearch(
    'https://fr.wikipedia.org/w/api.php?action=query&list=search'
    .'&srsearch=%22%7B%7Bouvrage%22+insource%3A%2F%5C%7B%5C%7B%5BoO%5Duvrage%2F'
    .'&formatversion=2&format=json&srnamespace=0&srlimit=100&srqiprofile=popular_inclinks_pv&srsort=last_edit_desc'
);
//new ScanWiki2DB($wiki, new DbAdapter(), new WikiBotConfig(), $list, 20);


// utilise une liste d'import wstat.fr
$list = PageList::FromFile(__DIR__.'/../resources/importISBN_nov.txt');
new ScanWiki2DB($wiki, new DbAdapter(), new WikiBotConfig(), $list, 0);


