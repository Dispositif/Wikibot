<?php

/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */
declare(strict_types=1);

namespace App\Application\CLI;

use App\Application\ExternLink\ExternRefWorker;
use App\Application\SignalHandler;
use App\Application\WikiBotConfig;
use App\Infrastructure\CirrusSearch;
use App\Infrastructure\Monitor\ConsoleLogger;
use App\Infrastructure\PageList;
use App\Infrastructure\ServiceFactory;

/**
 * Traitement synchrone des URL brutes http:// transformée en {lien web} ou {article}
 */

include __DIR__.'/../myBootstrap.php';

// todo VOIR EN BAS

/**
 * Floor on one full pass, because this CLI runs as a long-lived container
 * (restart: unless-stopped in compose.yaml) : Docker restarts the process the moment it
 * exits, so a pass that ends almost immediately — an empty draw, a CirrusSearch hiccup,
 * a list entirely filtered out by the journal — would otherwise re-draw in a tight loop.
 * Only covers the success path ; a crash bypasses it and is caught instead by Docker's
 * own restart backoff (100ms doubling, capped at 1 min).
 */
const MIN_CYCLE_SECONDS = 600;

$startedAt = time();

/** @noinspection PhpUnhandledExceptionInspection */
$wiki = ServiceFactory::getMediawikiFactory();
$logger = new ConsoleLogger();
$logger->colorMode = true;
//$logger->debug = true;
$botConfig = new WikiBotConfig($wiki, $logger);
$botConfig->setTaskName("🐭 Amélioration de références : URL ⇒ "); // 🐞🌐🧅🔗
$botConfig->setMaxTitles(WikiBotConfig::maxTitlesFromArgv($argv));

$botConfig->checkStopOnTalkpageOrException();

// TODO : \<ref[^\>]*\> et liste à puces * http://...
//
// srsort=random, not last_edit_desc : the query matches ~26k articles, of which the
// previous 3×500 "most recently edited" slice renewed far slower than the run cadence,
// so consecutive runs mostly re-served titles already in the analyzed journal ("Skip :
// déjà analysé"). Random draws a fresh sample from the whole corpus each time, and also
// reaches the ~16k articles that sroffset (capped at 10000) can never paginate to.
// Consequences of that choice, both intentional :
//  - srqiprofile is dropped : random sorting overrides any relevance profile.
//  - one single request instead of stream() : paginating a random sort reshuffles
//    between pages, so it returns duplicates and gaps rather than more coverage.
//  - OPTION_REVERSE is dropped : it only made sense to un-reverse the last_edit_desc order.
// See audits/audit-sources-listes-articles-2026-08.md
$cirrusSearch = new CirrusSearch(
    [
        'srsearch' => '"http" insource:/\<ref[^\>]*\> ?https?\:\/\/[^\<\ ]+ *\<\/ref/',
        'srlimit' => CirrusSearch::SRLIMIT_MAX, // 5000 with the bot account's apihighlimits, 500 otherwise
        'srsort' => CirrusSearch::SRSORT_RANDOM,
    ],
    [CirrusSearch::OPTION_APILOGIN => true, CirrusSearch::OPTION_CONTINUE => false]
);

// Sieve against the analyzed journal here, not title by title inside the worker loop.
// This used to filter on the flat article_externRef_edited.txt, a leftover from before
// the journal moved to MySQL : on a 5000-title draw it caught 8% and the worker then
// re-discovered all the rest one "Skip : déjà analysé" at a time. Two consequences of
// doing it up front : one query per 1000 titles instead of one SELECT per title, and
// the count printed below is the real workload — which is what the cron interval has
// to be derived from. The per-title wasAnalyzed() check stays in the worker as a guard
// against a concurrent run analyzing a title while this one is in flight.
$candidates = $cirrusSearch->getPageTitles();
$titles = ServiceFactory::getBotEditJournal(ExternRefWorker::ARTICLE_ANALYZED_FILENAME)
    ->filterNotAnalyzed($candidates, ExternRefWorker::JOURNAL_TASK);

$list = new PageList($titles);
echo sprintf(">%d dans liste (%d tirés, %d déjà analysés)\n", $list->count(), count($candidates), count($candidates) - $list->count());


// 2nd pass without Tor when the Tor fetch looks blocked (403/429/503, cf-mitigated,
// interstitial title/body markers) — on by default, self-identifies honestly (not a
// fake browser UA) on that direct pass. --no-direct-retry opts back out entirely.
// robots.txt observance — on by default, --no-robots-check opts out.
// See audits/synthese-anti-bot-crawling-tor-2026-08.md
$directRetryEnabled = !in_array('--no-direct-retry', $argv, true);
$respectRobotsTxt = !in_array('--no-robots-check', $argv, true);

$transformer = ServiceFactory::getExternRefTransformer($logger, $argv, true, $directRetryEnabled, $respectRobotsTxt);

$dryRun = in_array('--dry-run', $argv, true);
new ExternRefWorker($botConfig, $wiki, $list, $transformer, $dryRun);

echo "END of process\n";

// Skipped when a SIGTERM is already pending : that means `docker compose up -d` is
// waiting on stop_grace_period to recreate this container for a deploy, and sleeping
// here would just get the process SIGKILLed instead of exiting cleanly.
$remaining = MIN_CYCLE_SECONDS - (time() - $startedAt);
if ($remaining > 0 && !SignalHandler::isStopRequested()) {
    echo sprintf("Cycle court (%ds) : pause de %ds avant redémarrage du conteneur\n", time() - $startedAt, $remaining);
    sleep($remaining);
}
