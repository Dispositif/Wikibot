<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

namespace App\Application\CLI;

use App\Application\GoogleBooksWorker;
use App\Application\WikiBotConfig;
use App\Infrastructure\CirrusSearch;
use App\Infrastructure\GoogleApiQuota;
use App\Infrastructure\Monitor\ConsoleLogger;
use App\Infrastructure\PageList;
use App\Infrastructure\ServiceFactory;
use Exception;

/**
 * Searches for raw Google Books links (<ref…> and bullet list * ) and transforms them into {ouvrage}
 * Consumes GoogleBooks API daily quota
 */

include __DIR__.'/../CodexBot2_Bootstrap.php';

$quota = new GoogleApiQuota();
echo 'Google quota: '.$quota->getCount();

if ($quota->isQuotaReached()) {
    throw new Exception("Google Books API quota reached => exit");
}

$wiki = ServiceFactory::getMediawikiFactory();
$logger = new ConsoleLogger();
//$logger->debug = true; // todo option
//$logger->verbose = true;
//$logger->colorMode = true;
$bot = new WikiBotConfig($wiki, $logger);
$bot->checkStopOnTalkpageOrException();
$bot->setTaskName("🌐📘 Amélioration bibliographique : lien Google Books ⇒ {ouvrage}");

// The previous query ('"https://books.google" insource:/\<ref[^\>]*\> *https\:\/\/books\.google/')
// was down to 9 hits on fr.wikipedia : the task was done, and the endless "Skip : déjà
// analysé" came from re-serving the same exhausted set. It was also blind to three things
// GoogleTransformer does handle, hence the wider regex below (~161 hits, measured 2026-08-14) :
//  - https, but also http, and play.google.*
//  - the new URL format www.google.*/books/edition/<slug>/<id> — 117 articles have one in a
//    <ref>, and GoogleTransformer has supported it since the 2026-08-05 fix (see
//    audits/audit-google-livres-nouveau-format-url.md)
//  - "* http…" bullet lists, handled by extractGoogleExternalBullets()
// Kept narrower than a bare insource:/books\.google/ (186k hits) on purpose : those are
// overwhelmingly links already inside a {{ouvrage|lire en ligne=}}, nothing left to convert.
// The leading bare "google" term is not redundant with the regex : it restricts the document
// set the regex then scans. Without it the search reports "the regex search timed out, so
// only partial results are available" and the hit count wobbles run to run.
// See audits/audit-sources-listes-articles-2026-08.md
$list = new CirrusSearch(
    [
        'srsearch' => '"google" insource:/(\<ref[^\>]*\>|\*) *https?\:\/\/((books|play)\.google\.|www\.google\.[a-z\.]+\/books\/)/',
        'srnamespace' => '0',
        'srlimit' => CirrusSearch::SRLIMIT_MAX, // 5000 with the bot account's apihighlimits, 500 otherwise
        'srqiprofile' => CirrusSearch::SRQIPROFILE_POPULAR_INCLINKS_PV,
    ],
    // OPTION_CONTINUE off : it promised a resumable cursor it never delivered under
    // Docker (the offset file lands in /app/resources/, which no compose service
    // bind-mounts, so it dies with each --rm container). Moot here anyway — srlimit=max
    // returns the whole ~161-article corpus in a single request, nothing to paginate.
    [CirrusSearch::OPTION_APILOGIN => true, CirrusSearch::OPTION_CONTINUE => false]
);
$titles = $list->getPageTitles();
echo 'CirrusSearch: '.count($titles).' titles found';
$list = new PageList($titles);

// --dry-run can appear anywhere; strip it before reading the positional page title.
$dryRun = in_array('--dry-run', $argv, true);
$positionalArgs = array_values(array_filter($argv, static fn ($a) => $a !== '--dry-run'));
if (!empty($positionalArgs[1])) {
    $list = new PageList([trim($positionalArgs[1])]);
}

new GoogleBooksWorker($bot, $wiki, $list, $dryRun);
