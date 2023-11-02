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
use App\Domain\ExternLink\ExternRefTransformer;
use App\Domain\Publisher\ExternMapper;
use App\Infrastructure\CirrusSearch;
use App\Infrastructure\InternetArchiveAdapter;
use App\Infrastructure\InternetDomainParser;
use App\Infrastructure\Monitor\ConsoleLogger;
use App\Infrastructure\Monitor\NullStats;
use App\Infrastructure\Monitor\StatsRedis;
use App\Infrastructure\Monitor\StatsSqlite3;
use App\Infrastructure\PageList;
use App\Infrastructure\ServiceFactory;
use App\Infrastructure\WikiwixAdapter;

/**
 * Traitement synchrone des URL brutes http:// transformée en {lien web} ou {article}
 */

include __DIR__.'/../myBootstrap.php';

// --page="Skateboard" --stats=redis --stats=sqlite --debug --verbose
echo "OPTIONS: --debug --verbose --stats=redis --stats=sqlite --page=\"Skateboard\" --offset=1000 \n";
$options = getopt('', ['page::', 'debug', 'verbose', 'stats::', 'offset::']);

/** @noinspection PhpUnhandledExceptionInspection */
$wiki = ServiceFactory::getMediawikiFactory();

$stats = new NullStats();
if (isset($options["stats"]) && $options["stats"] === 'redis') {
    $stats = new StatsRedis();
}
if (isset($options["stats"]) && $options["stats"] === 'sqlite') {
    $stats = new StatsSqlite3();
}
$offset = $options['offset'] ?? 0;

$logger = new ConsoleLogger($stats);
//$logger->colorMode = true;
$logger->debug = isset($options['debug']);
$logger->verbose = isset($options['verbose']);

$botConfig = new WikiBotConfig($wiki, $logger);
$botConfig->setTaskName("🌐 Amélioration de références : URL ⇒ "); // 🐞🌐🔗🧅

$botConfig->checkStopOnTalkpageOrException();

if (!empty($options['page'])) {
    $list = new PageList([trim($options['page'])]);

    // delete Title from edited.txt
    $file = __DIR__ . '/../resources/article_externRef_edited.txt';
    $text = file_get_contents($file);
    $newText = str_replace(trim($argv[1]) . "\n", '', $text);
    if (!empty($text) && $text !== $newText) {
        @file_put_contents($file, $newText);
    }
    $botConfig->setTaskName('🐞' . $botConfig->getTaskName());
} else {
    // TODO : liste à puces * http://...
    // RANDOM :
    // https://www.mediawiki.org/wiki/API:Search
    $list = new CirrusSearch(
        [
            'srnamespace' => 0,
            'srsearch' => '"http" insource:/\<ref[^\>]*\> ?http/',
            'srlimit' => '500', // 500 max
            'srsort' => 'none', //'random',
//            'sroffset' => $offset, //default: 0
            'srqiprofile' => 'popular_inclinks_pv', /* Notation basée principalement sur le nombre de vues de la page */
        ],
        ['continue' => true]
    );

    // filter titles already in edited.txt
    $titles = $list->getPageTitles();
    echo '> before filtering: '. count($titles)." articles.\n";
    unset($list);
    $edited = file(__DIR__ . '/../resources/article_externRef_edited.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $titles = array_diff($titles, $edited);
    $list = new PageList($titles);
}


//// HACK list manuelle
//$filename = __DIR__ . '/../../../resources/wikiwix-log-11-01.txt';
//echo "LISTE FROM " . $filename . "\n";
//$titles = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
//// filtering
//echo '> before filtering: ' . count($titles) . " articles.\n";
//$edited = file(__DIR__ . '/../resources/article_externRef_edited.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
//$titles = array_diff($titles, $edited);
//$list = new PageList($titles);
//// end HACK list manuelle


echo ">" . $list->count() . " dans liste\n";
if ($list->count() === 0) {
    echo "END of process: EMPTY ARTICLE LIST\n";
    sleep(120);
    exit(1);
}

$httpClient = ServiceFactory::getHttpClient();
$wikiwix = new WikiwixAdapter($httpClient, $logger);
$internetArchive = new InternetArchiveAdapter($httpClient, $logger);

$domainParser = new InternetDomainParser();
$transformer = new ExternRefTransformer(
    new ExternMapper($logger),
    ServiceFactory::getHttpClient(true),
    $domainParser,
    $logger,
    [$wikiwix, $internetArchive, $wikiwix]
);

new ExternRefWorker($botConfig, $wiki, $list, $transformer);

echo "END of process\n";
sleep(60);
