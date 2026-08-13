<?php

/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */
declare(strict_types=1);

namespace App\Application\CLI;

use App\Application\ExternLink\RawExternLinkWorker;
use App\Application\WikiBotConfig;
use App\Domain\ExternLink\ExternRefTransformer;
use App\Domain\ExternLink\Raw\RawExternLinkParser;
use App\Domain\ExternLink\Raw\RawExternLinkTransformer;
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
 * Lot 5 : "[url Libellé]" (bracketed, manuscript citations) => {{lien web}}/{{article}}/
 * {{lien brisé}}, blending the crawl with the manuscript's own titre/site/date/auteur/
 * langue. Deliberately independent from externRefProcess.php (own dedup file, own
 * CirrusSearch query, own worker) -- see RawExternLinkWorker's docblock. Reuses
 * ExternRefTransformer completely unchanged as the crawl engine (RawExternLinkTransformer
 * calls it via its interface, never modifies it).
 */

include __DIR__.'/../myBootstrap.php';

// --page="Skateboard" --stats=redis --stats=sqlite --debug --verbose --dry-run --no-direct-retry --no-robots-check
// No --auto flag : type "auto" at the first confirmation prompt instead
// (WorkerCLITrait::autoOrYesConfirmation() already switches modeAuto for the rest of
// the run from there -- a separate CLI flag would need modeAuto threaded through
// AbstractBotTaskWorker's constructor, which calls run() internally before returning).
echo "OPTIONS: --debug --verbose --stats=redis --stats=sqlite --page=\"Skateboard\" --nofilter --dry-run --no-direct-retry --no-robots-check \n";
$options = getopt('', ['page::', 'debug', 'verbose', 'stats::', 'nofilter', 'dry-run', 'no-direct-retry', 'no-robots-check']);
$dryRun = isset($options['dry-run']);
$directRetryEnabled = !isset($options['no-direct-retry']);
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
$logger->debug = isset($options['debug']);
$logger->verbose = isset($options['verbose']);

$botConfig = new WikiBotConfig($wiki, $logger);
$botConfig->setTaskName("🌐🔖 Conversion de lien externe manuel :");

$botConfig->checkStopOnTalkpageOrException();

$torClient = ServiceFactory::getHttpClient(true);
$editedFile = __DIR__ . '/../resources/article_rawExternRef_edited.txt';

if (!empty($options['page'])) {
    $list = new PageList([trim($options['page'])]);

    $text = @file_get_contents($editedFile);
    $newText = str_replace(trim($options['page']) . "\n", '', (string)$text);
    if (!empty($text) && $text !== $newText) {
        @file_put_contents($editedFile, $newText);
    }
    $botConfig->setTaskName('🐞' . $botConfig->getTaskName());
} else {
    // Same angle as the Lot 0 mining queries (rawExternLinkCorpusScan.php) : any
    // bracketed raw link inside a <ref>, prioritized by inbound-link popularity.
    $list = new CirrusSearch(
        [
            'srnamespace' => '0',
            'srsearch' => '"https" insource:/\<ref[^\>]*\> ?\[https?\:[^\]]+\]/',
            'srlimit' => '500',
            'srsort' => CirrusSearch::SRSORT_NONE,
            'srqiprofile' => CirrusSearch::SRQIPROFILE_POPULAR_INCLINKS_PV,
        ],
        [CirrusSearch::OPTION_CONTINUE => true]
    );

    if (!isset($options['nofilter'])) {
        $titles = $list->getPageTitles();
        echo '> before filtering: ' . count($titles) . " articles.\n";
        unset($list);
        $edited = file($editedFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $titles = array_diff($titles, $edited);
        $list = new PageList($titles);
    }
}

echo ">" . $list->count() . " dans liste\n";
if ($list->count() === 0) {
    echo "END of process: EMPTY ARTICLE LIST\n";
    sleep(120);
    exit(1);
}

try {
    $httpClient = ServiceFactory::getHttpClient();
    $wikiwix = new WikiwixAdapter($httpClient, $logger);
    $internetArchive = new InternetArchiveAdapter($httpClient, $logger);
    $domainParser = new InternetDomainParser();

    $externRefTransformer = new ExternRefTransformer(
        new ExternMapper($logger),
        $torClient,
        $domainParser,
        $logger,
        [$internetArchive, $wikiwix],
        ServiceFactory::getExternLinkCheckRepository($argv),
        $directRetryEnabled ? $httpClient : null,
        $respectRobotsTxt
    );

    $transformer = new RawExternLinkTransformer(new RawExternLinkParser(), $externRefTransformer);

    new RawExternLinkWorker($botConfig, $wiki, $list, $transformer, $dryRun);
} finally {
    echo "END of process\n";
    sleep(5);
}
