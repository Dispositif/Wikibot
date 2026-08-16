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
use App\Infrastructure\Monitor\ConsoleLogger;
use App\Infrastructure\Monitor\NullStats;
use App\Infrastructure\Monitor\StatsRedis;
use App\Infrastructure\Monitor\StatsSqlite3;
use App\Infrastructure\PageList;
use App\Infrastructure\ServiceFactory;

/**
 * Traitement synchrone des URL brutes http:// transformée en {lien web} ou {article}
 */

include __DIR__.'/../myBootstrap.php';

// --page="Skateboard" --stats=redis --stats=sqlite --debug --verbose --dry-run --no-direct-retry --no-robots-check
echo "OPTIONS: --debug --verbose --stats=redis --stats=sqlite --page=\"Skateboard\" --nofilter --dry-run --no-db --no-direct-retry --no-robots-check \n";
$options = getopt('', ['page::', 'debug', 'verbose', 'stats::', 'nofilter', 'dry-run', 'no-direct-retry', 'no-robots-check']);
$dryRun = isset($options['dry-run']);
// 2nd pass without Tor when the Tor fetch looks blocked (403/429/503, cf-mitigated,
// interstitial title/body markers) — on by default, self-identifies honestly (not a
// fake browser UA) on that direct pass. --no-direct-retry opts back out entirely.
// See audits/synthese-anti-bot-crawling-tor-2026-08.md
$directRetryEnabled = !isset($options['no-direct-retry']);
// robots.txt observance — on by default, --no-robots-check opts out (e.g. debugging one URL)
$respectRobotsTxt = !isset($options['no-robots-check']);

/** @noinspection PhpUnhandledExceptionInspection */
$wiki = ServiceFactory::getMediawikiFactory();

$stats = new NullStats();
if (isset($options["stats"]) && $options["stats"] === 'redis') {
    $stats = new StatsRedis();
}
if (isset($options["stats"]) && $options["stats"] === 'sqlite') {
    $stats = new StatsSqlite3();
}

$logger = new ConsoleLogger($stats);
//$logger->colorMode = true;
$logger->debug = isset($options['debug']);
$logger->verbose = isset($options['verbose']);

$botConfig = new WikiBotConfig($wiki, $logger);
$botConfig->setTaskName("🌐 Amélioration de références : URL ⇒ "); // 🐞🌐🔗🧅
$botConfig->setMaxTitles(WikiBotConfig::maxTitlesFromArgv($argv));

$botConfig->checkStopOnTalkpageOrException();

// instanciate the transformer (and its Tor client) now, so there is no CirrusSearch
// request if there is a Tor connection error
$transformer = ServiceFactory::getExternRefTransformer($logger, $argv, true, $directRetryEnabled, $respectRobotsTxt);

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
    // https://www.mediawiki.org/wiki/API:Search
    // https://www.mediawiki.org/wiki/Help:CirrusSearch#Explicit_sort_orders
    //
    // Was SRSORT_NONE + OPTION_CONTINUE, i.e. a persisted sroffset cursor walking the
    // corpus 500 titles at a time. That cursor never survived a run in Docker : it is
    // written to /app/resources/cirrusSearch-<hash>.txt, a path no compose service
    // bind-mounts, so it died with each `--rm` container and every run restarted at
    // offset 0. This worker had been re-drawing the same 500 top-relevance titles —
    // all long since analyzed — which is why it went silent with nothing to edit.
    //
    // Random instead of restoring the cursor : sroffset is capped at 10000 server-side,
    // so on 59k matches a cursor could only ever reach a sixth of the corpus before
    // locking up. srqiprofile dropped, meaningless under a random sort.
    // See audits/audit-sources-listes-articles-2026-08.md
    $list = new CirrusSearch(
        [
            'srnamespace' => '0',
            'srsearch' => '"https" insource:/\<ref[^\>]*\> ?https?\:/',
            'srlimit' => CirrusSearch::SRLIMIT_MAX, // 5000 with the bot account's apihighlimits, 500 otherwise
            'srsort' => CirrusSearch::SRSORT_RANDOM,
        ],
        [CirrusSearch::OPTION_APILOGIN => true, CirrusSearch::OPTION_CONTINUE => false]
    );

    // Sieved against the analyzed journal, not the flat edited.txt : the journal is the
    // authority the worker itself consults, and doing it here means one query per 1000
    // titles instead of one SELECT per title inside the loop.
    if (!isset($options['nofilter'])) {
        $candidates = $list->getPageTitles();
        $titles = ServiceFactory::getBotEditJournal(ExternRefWorker::ARTICLE_ANALYZED_FILENAME)
            ->filterNotAnalyzed($candidates, ExternRefWorker::JOURNAL_TASK);
        echo sprintf('> %d tirés, %d déjà analysés' . "\n", count($candidates), count($candidates) - count($titles));
        $list = new PageList($titles);
    }
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

try {
    new ExternRefWorker($botConfig, $wiki, $list, $transformer, $dryRun);
} finally {
    echo "END of process\n";
    sleep(120);
}
