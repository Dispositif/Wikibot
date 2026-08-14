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

$botConfig->checkStopOnTalkpageOrException();

$cirrusSearch = new CirrusSearch(
    [
        'srsearch' => 'insource:/\<ref[^\>]*\> ?\{\{ ?([Ll]ien web|[Aa]rticle)[ |]/',
        'srlimit' => '500',
        'srqiprofile' => CirrusSearch::SRQIPROFILE_DEFAULT,
        'srsort' => CirrusSearch::SRSORT_LAST_EDIT_DESC,
    ],
    [CirrusSearch::OPTION_REVERSE => true, CirrusSearch::OPTION_CONTINUE => false]
);

$edited = array_flip(
    file(__DIR__ . '/../resources/article_existingRef_edited.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []
);
$filtered = [];
foreach ($cirrusSearch->stream(maxPages: 3, sleepBetweenPages: 3) as $title) {
    if (!isset($edited[$title])) {
        $filtered[] = $title;
    }
}
$list = new PageList($filtered);
echo ">" . $list->count() . " dans liste\n";

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
