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
use App\Infrastructure\CirrusSearch;
use App\Infrastructure\DbAdapter;
use App\Infrastructure\Monitor\ConsoleLogger;
use App\Infrastructure\PageList;
use App\Infrastructure\ServiceFactory;


include __DIR__.'/../myBootstrap.php';

/**
 * From a titles list, scan the wiki and add the {ouvrage} citations into the DB
 */

$wiki = ServiceFactory::getMediawikiFactory();

// import manuel : > php ouvrageScanProcess.php "Bla"
if (!empty($argv[1])) {
    echo "Ajout manuel...\n";
    $list = new PageList([trim($argv[1])]);
    new ScanWiki2DB($wiki, new DbAdapter(), new WikiBotConfig($wiki), $list, 15);
    exit;
}


// Catégories : Article potentiellement bon, Article potentiellement de qualité
//echo "Catégories : Article potentiellement bon, Article potentiellement de qualité\n";
//$ba = PageList::FromWikiCategory('Article potentiellement de qualité');
//$adq = PageList::FromWikiCategory('Article potentiellement bon');
//$list = new PageList(array_merge($ba->getPageTitles(),$adq->getPageTitles()));
//new ScanWiki2DB($wiki, new DbAdapter(), new WikiBotConfig(), $list, 20);
//exit;


// Random draw of articles containing an {ouvrage}, same recipe as the extern-link
// workers (see audits/audit-sources-listes-articles-2026-08.md §10). It replaces
// "srsort=last_edit_desc + OPTION_CONTINUE" :
//  - the persisted sroffset cursor was written to /app/resources/cirrusSearch-<hash>.txt,
//    a path no compose.yaml service bind-mounts, so `docker compose run --rm` destroyed it
//    and every run restarted at offset 0 on the same head-of-list titles. Restoring the
//    file would not fix it either : sroffset is capped at 10000 server-side.
//  - "prefix:j" restricted the whole ISBN pipeline to article titles starting with "j",
//    i.e. 3191 of the 51095 matching articles. Added along with the cursor in d644e92
//    (2023-12) with no rationale, and absent from every earlier revision of this query :
//    leftover debug scoping, removed.
// srqiprofile stays dropped : a random sort overrides any relevance profile.
echo "articles contenant un {ouvrage} (tirage aléatoire) \n";
$cirrusSearch = new CirrusSearch(
    [
        'srsearch' => '"{{ouvrage" insource:/\{\{[oO]uvrage/',
        'srnamespace' => '0',
        'srlimit' => CirrusSearch::SRLIMIT_MAX, // 5000 with the bot account's apihighlimits, 500 otherwise
        'srsort' => CirrusSearch::SRSORT_RANDOM,
    ],
    [CirrusSearch::OPTION_APILOGIN => true, CirrusSearch::OPTION_CONTINUE => false]
);

// Sieve against page_ouvrages up front, the equivalent here of the extern-link workers'
// journal pre-filter : this pipeline has no bot_page_analyzed entry, ScanWiki2DB dedups
// through insertPageOuvrages() alone — and only after paying the page fetch (see
// DbAdapter::filterNotScanned).
$db = new DbAdapter();
$candidates = $cirrusSearch->getPageTitles();
$notScanned = $db->filterNotScanned($candidates);

// ScanWiki2DB throttles at ~6s per title, so a full 5000-title draw would run for
// 8 hours in a `restart: "no"` one-shot container. The draw stays wide (a wide random
// sample is what keeps successive runs fresh, and it costs one request), the *run* is
// what gets bounded — to the 500 titles the previous srlimit already implied.
// Override with --max-titles=N.
$maxTitles = WikiBotConfig::maxTitlesFromArgv($argv) ?? 500;
$titles = array_slice($notScanned, 0, $maxTitles);

$list = new PageList($titles);
echo sprintf(
    ">%d dans liste (%d tirés, %d déjà scannés, plafond %d)\n",
    $list->count(), count($candidates), count($candidates) - count($notScanned), $maxTitles
);

$logger = new ConsoleLogger();
new ScanWiki2DB($wiki, $db, new WikiBotConfig($wiki, $logger), $list, 11);

exit;

//// utilise une liste d'import wstat.fr
// echo "Liste d'après wstat.fr\n";
//$list = PageList::FromFile(__DIR__.'/../resources/importISBN_nov.txt');
//new ScanWiki2DB($wiki, new DbAdapter(), new WikiBotConfig($wiki), $list, 11);


