<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

namespace App\Application\CLI;

use App\Application\GoogleBooksWorker;
use App\Application\WikiBotConfig;
use App\Domain\Exceptions\QuotaExceededException;
use App\Infrastructure\CirrusSearch;
use App\Infrastructure\GoogleApiQuota;
use App\Infrastructure\Monitor\ConsoleLogger;
use App\Infrastructure\PageList;
use App\Infrastructure\ServiceFactory;

/**
 * Searches for raw Google Books links (<ref…> and bullet list * ) and transforms them into {ouvrage}
 * Consumes GoogleBooks API daily quota
 */

include __DIR__.'/../CodexBot2_Bootstrap.php';

/**
 * Pause before exiting on a quota stop, so a caller that relaunches on exit (a tight
 * cron, or restart: unless-stopped should this ever become a non-stop service) cannot
 * spin. The daily quota resets at GoogleApiQuota::REBOOT_HOUR, so there is nothing to
 * gain from retrying sooner than this either way.
 */
const QUOTA_SLEEP_SECONDS = 600;

$quota = new GoogleApiQuota();
echo 'Google quota: ' . $quota->getCount() . "\n";

if ($quota->isQuotaReached()) {
    echo sprintf(
        "Google Books API quota atteint (%d/%d) : pause de %ds puis sortie\n",
        $quota->getCount(),
        GoogleApiQuota::SAFE_DAILY_QUOTA,
        QUOTA_SLEEP_SECONDS
    );
    sleep(QUOTA_SLEEP_SECONDS);
    exit(0);
}

$wiki = ServiceFactory::getMediawikiFactory();
$logger = new ConsoleLogger();
//$logger->debug = true; // todo option
//$logger->verbose = true;
//$logger->colorMode = true;
$bot = new WikiBotConfig($wiki, $logger);
$bot->checkStopOnTalkpageOrException();
$bot->setTaskName("🌐📘 Amélioration bibliographique : lien Google Books ⇒ {ouvrage}");

// This query MUST stay a faithful mirror of GoogleTransformer::extractAllGoogleRefs() and
// extractGoogleExternalBullets(). Anything it matches that the transformer cannot convert
// is not merely wasted work : the worker records the page as analyzed before deciding
// there is nothing to change, so a false positive is burned for good and would still be
// skipped after the transformer is one day widened (undo with forgetAnalyzed()).
//
// See audits/audit-sources-listes-articles-2026-08.md §11
$list = new CirrusSearch(
    [
        'srsearch' => '"google" insource:/(\<ref[^\>]*\>|\{\{ref\||\*) ?https?\:\/\/(books\.google\.[^\<\ ]*[\?\&](id|isbn)\=|www\.google\.[a-z\.]+\/books\/edition\/)[^\<\ ]* ?(\<\/ref\>|\}\}|$)/',
        'srnamespace' => '0',
        'srlimit' => CirrusSearch::SRLIMIT_MAX, // 5000 with the bot account's apihighlimits, 500 otherwise
        'srqiprofile' => CirrusSearch::SRQIPROFILE_POPULAR_INCLINKS_PV,
    ],
    // OPTION_CONTINUE off : it promised a resumable cursor it never delivered under
    // Docker (the offset file lands in /app/resources/, which no compose service
    // bind-mounts, so it dies with each --rm container). Moot here anyway — srlimit=max
    // returns this small corpus in a single request, nothing to paginate.
    [CirrusSearch::OPTION_APILOGIN => true, CirrusSearch::OPTION_CONTINUE => false]
);
$titles = $list->getPageTitles();
echo 'CirrusSearch: ' . count($titles) . " titles found\n";
$list = new PageList($titles);

// --dry-run can appear anywhere; strip it before reading the positional page title.
$dryRun = in_array('--dry-run', $argv, true);
$bot->setMaxTitles(WikiBotConfig::maxTitlesFromArgv($argv));
$positionalArgs = array_values(array_filter($argv, static fn ($a) => $a !== '--dry-run' && !str_starts_with((string)$a, '--max-titles')));
if (!empty($positionalArgs[1])) {
    $list = new PageList([trim($positionalArgs[1])]);
}

// The quota can also run out mid-run : GoogleTransformer throws as soon as it does, which
// used to surface as an uncaught fatal and a bare stack trace. Same pause as the pre-flight
// check above, for the same reason.
try {
    new GoogleBooksWorker($bot, $wiki, $list, $dryRun);
} catch (QuotaExceededException $e) {
    echo sprintf(
        "Google Books API quota épuisé en cours de run (%d/%d) : pause de %ds puis sortie\n",
        (new GoogleApiQuota())->getCount(),
        GoogleApiQuota::SAFE_DAILY_QUOTA,
        QUOTA_SLEEP_SECONDS
    );
    sleep(QUOTA_SLEEP_SECONDS);
    exit(0);
}
