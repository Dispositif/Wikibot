<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\CLI;

use App\Application\OuvrageScan\ScanWiki2DB;
use App\Application\WikiBotConfig;
use App\Infrastructure\DbAdapter;
use App\Infrastructure\PageList;
use App\Infrastructure\ServiceFactory;


include __DIR__.'/../myBootstrap.php';

/**
 * From a titles list, scan the wiki and add the {ouvrage} citations into the DB
 */

$wiki = ServiceFactory::getMediawikiFactory();

// import manuel : > php wikiScanProcess.php "Bla"
if (!empty($argv[1])) {
    echo "Ajout manuel...\n";
    $list = new PageList([trim($argv[1])]);
    new ScanWiki2DB($wiki, new DbAdapter(), new WikiBotConfig(), $list, 15);
    exit;
}


// Catégories : Article potentiellement bon, Article potentiellement de qualité
//echo "Catégories : Article potentiellement bon, Article potentiellement de qualité\n";
//$ba = PageList::FromWikiCategory('Article potentiellement de qualité');
//$adq = PageList::FromWikiCategory('Article potentiellement bon');
//$list = new PageList(array_merge($ba->getPageTitles(),$adq->getPageTitles()));
//new ScanWiki2DB($wiki, new DbAdapter(), new WikiBotConfig(), $list, 20);
//exit;


// 100 dernier articles édités contenant un {ouvrage}
//echo "1000 dernier articles édités contenant un {ouvrage} \n";
//$list = new CirrusSearch(
//    [
//        'srsearch' => '"{{ouvrage" insource:/\{\{[oO]uvrage/',
//        'srnamespace' => '0',
//        'srlimit' => '1000',
//        'srqiprofile' => 'popular_inclinks_pv',
//        'srsort' => 'last_edit_desc',
//    ]
//);
//new ScanWiki2DB($wiki, new DbAdapter(), new WikiBotConfig(), $list, 11);



// utilise une liste d'import wstat.fr
 echo "Liste d'après wstat.fr\n";
$list = PageList::FromFile(__DIR__.'/../resources/importISBN_nov.txt');
new ScanWiki2DB($wiki, new DbAdapter(), new WikiBotConfig(), $list, 0);


