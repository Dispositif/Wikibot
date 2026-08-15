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

// --page="Skateboard" --stats=redis --stats=sqlite --debug --verbose --dry-run --no-direct-retry --no-robots-check --auto --reject-uncertain
// --auto : fully unsupervised (no confirmation prompts at all, not even for a
// site/data conflict). SemiAuto merges are still applied, not skipped, but with the
// bot flag forced off and a warning note in the edit summary -- see
// RawExternLinkWorker::processRefContent(). Without --auto, every SemiAuto merge
// always needs an interactive "y"/"n" regardless of anything typed at earlier prompts.
// --reject-uncertain : also unsupervised (implies --auto on its own, no need to pass
// both), but SemiAuto merges are SKIPPED instead of applied unflagged -- nothing is
// published for them at all. Use for a fully unattended run where nobody patrols
// Recent Changes for the unflagged/warned edits --auto alone would leave behind.
echo "OPTIONS: --debug --verbose --stats=redis --stats=sqlite --page=\"Skateboard\" --nofilter --dry-run --no-direct-retry --no-robots-check --auto --reject-uncertain \n";
$options = getopt('', ['page::', 'debug', 'verbose', 'stats::', 'nofilter', 'dry-run', 'no-direct-retry', 'no-robots-check', 'auto', 'reject-uncertain']);
$dryRun = isset($options['dry-run']);
$fullAuto = isset($options['auto']);
$rejectUncertain = isset($options['reject-uncertain']);
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
$botConfig->setMaxTitles(WikiBotConfig::maxTitlesFromArgv($argv));

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
    // bracketed raw link inside a <ref>.
    //
    // Same fix as extern-ref, same cause : OPTION_CONTINUE's sroffset cursor is written
    // to /app/resources/, which no compose service bind-mounts, so it died with every
    // `--rm` container and each run restarted at offset 0 on the same 500 titles.
    // Random rather than a restored cursor, since sroffset caps at 10000 and this query
    // matches 45k articles. srqiprofile dropped, meaningless under a random sort.
    // See audits/audit-sources-listes-articles-2026-08.md
    $list = new CirrusSearch(
        [
            'srnamespace' => '0',
            'srsearch' => '"https" insource:/\<ref[^\>]*\> ?\[https?\:[^\]]+\]/',
            'srlimit' => CirrusSearch::SRLIMIT_MAX, // 5000 with the bot account's apihighlimits, 500 otherwise
            'srsort' => CirrusSearch::SRSORT_RANDOM,
        ],
        [CirrusSearch::OPTION_APILOGIN => true, CirrusSearch::OPTION_CONTINUE => false]
    );

    // Sieved against the analyzed journal rather than the flat edited.txt : same
    // authority the worker consults, one query per 1000 titles instead of one per title.
    if (!isset($options['nofilter'])) {
        $candidates = $list->getPageTitles();
        $titles = ServiceFactory::getBotEditJournal(RawExternLinkWorker::ARTICLE_ANALYZED_FILENAME)
            ->filterNotAnalyzed($candidates, RawExternLinkWorker::JOURNAL_TASK);
        echo sprintf('> %d tirés, %d déjà analysés' . "\n", count($candidates), count($candidates) - count($titles));
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

    new RawExternLinkWorker($botConfig, $wiki, $list, $transformer, $dryRun, $fullAuto, $rejectUncertain);
} finally {
    echo "END of process\n";
    sleep(5);
}
