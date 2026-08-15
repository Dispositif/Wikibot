<?php

/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */
declare(strict_types=1);

namespace App\Application\CLI;

use App\Application\ExternLink\ExistingRefWorker;
use App\Application\WikiBotConfig;
use App\Domain\ExternLink\Existing\ExistingRefTransformer;
use App\Domain\ExternLink\ExternRefTransformer;
use App\Domain\Publisher\ExternMapper;
use App\Infrastructure\CirrusSearch;
use App\Infrastructure\InternetArchiveAdapter;
use App\Infrastructure\InternetDomainParser;
use App\Infrastructure\Monitor\ConsoleLogger;
use App\Infrastructure\PageList;
use App\Infrastructure\ServiceFactory;
use App\Infrastructure\WikiwixAdapter;

/**
 * Re-crawls the URL of ALREADY-EXISTING {{lien web}}/{{article}} citations -- refreshes
 * 'consulté le' + fills gaps on a live page, converts to {{Lien brisé}}/an archived copy
 * when it's gone. See ExistingRefTransformer's docblock for the merge rules.
 *
 * Test/deployment phase (per ExistingRefWorker's docblock -- kept independent of
 * extern-ref/last-extern-ref for now) : same discovery angle as lastExternRefProcess.php,
 * a CirrusSearch on the most-recently-edited pages, filtered down to a <ref> immediately
 * wrapping a {{lien web}}/{{article}} usage -- ExistingRefTransformer::detectExistingTemplate()
 * only handles that shape anyway (MVP scope, see its docblock), so this narrows the query
 * to pages actually worth visiting rather than every citation on Wikipedia.
 */

include __DIR__.'/../myBootstrap.php';

/** @noinspection PhpUnhandledExceptionInspection */
$wiki = ServiceFactory::getMediawikiFactory();
$logger = new ConsoleLogger();
$logger->colorMode = true;
//$logger->debug = true;
$botConfig = new WikiBotConfig($wiki, $logger);
$botConfig->setTaskName("☑️ Consultation de {lien web} {article} :"); // ✔☑️✅
$botConfig->setMaxTitles(WikiBotConfig::maxTitlesFromArgv($argv));

$botConfig->checkStopOnTalkpageOrException();

// Same recipe as lastExternRefProcess.php, for the same reason : the previous
// last_edit_desc + stream(3) drew the 1500 most-recently-edited matches, a slice
// renewing slower than the run cadence, so successive runs mostly re-served titles the
// journal had already seen. Random draws a fresh sample from the ~400k corpus instead —
// measured overlap between two consecutive draws : 0.2%.
// srqiprofile and OPTION_REVERSE dropped : both are meaningless under a random sort.
//
// This query does report "the regex search timed out, so only partial results are
// available" (echoed by CirrusSearch::echoApiWarnings) : [Ll]ien / [Aa]rticle are
// character classes, so no trigram can be extracted to accelerate the scan. Left as is
// on purpose — the alternatives all cost more than they fix. A literal "ref" term
// removes the timeout but drops the corpus from 400k to 102k, and hastemplate: filters
// do not prevent it. The timeout only makes totalhits an estimate and the scan partial ;
// the draw still fills srlimit and stays fresh, which is all this needs.
// See audits/audit-sources-listes-articles-2026-08.md
$cirrusSearch = new CirrusSearch(
    [
        'srsearch' => 'insource:/\<ref[^\>]*\> ?\{\{ ?([Ll]ien web|[Aa]rticle)[ |]/',
        'srlimit' => CirrusSearch::SRLIMIT_MAX, // 5000 with the bot account's apihighlimits, 500 otherwise
        'srsort' => CirrusSearch::SRSORT_RANDOM,
    ],
    [CirrusSearch::OPTION_APILOGIN => true, CirrusSearch::OPTION_CONTINUE => false]
);

// Sieved against the analyzed journal here rather than title by title in the worker
// loop : one query per 1000 titles, and the count below is the real workload.
$candidates = $cirrusSearch->getPageTitles();
$titles = ServiceFactory::getBotEditJournal(ExistingRefWorker::ARTICLE_ANALYZED_FILENAME)
    ->filterNotAnalyzed($candidates, ExistingRefWorker::JOURNAL_TASK);

$list = new PageList($titles);
echo sprintf(">%d dans liste (%d tirés, %d déjà analysés)\n", $list->count(), count($candidates), count($candidates) - $list->count());

$httpClient = ServiceFactory::getHttpClient();
$wikiwix = new WikiwixAdapter($httpClient, $logger);
$internetArchive = new InternetArchiveAdapter($httpClient, $logger);

// Same anti-bot posture as extern-ref/raw-extern-ref : Tor first, then a direct 2nd pass
// only when the Tor fetch looks blocked, honoring robots.txt by default. See
// audits/synthese-anti-bot-crawling-tor-2026-08.md
$directRetryEnabled = !in_array('--no-direct-retry', $argv, true);
$respectRobotsTxt = !in_array('--no-robots-check', $argv, true);

$domainParser = new InternetDomainParser();
$externRefTransformer = new ExternRefTransformer(
    new ExternMapper($logger),
    ServiceFactory::getHttpClient(true),
    $domainParser,
    $logger,
    [$internetArchive, $wikiwix],
    ServiceFactory::getExternLinkCheckRepository($argv),
    $directRetryEnabled ? $httpClient : null,
    $respectRobotsTxt
);

$transformer = new ExistingRefTransformer($externRefTransformer);

$dryRun = in_array('--dry-run', $argv, true);
new ExistingRefWorker($botConfig, $wiki, $list, $transformer, $dryRun);

echo "END of process\n";
