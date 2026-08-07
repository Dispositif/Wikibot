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
use App\Infrastructure\PageList;
use App\Infrastructure\ServiceFactory;
use App\Infrastructure\WikiwixAdapter;

/**
 * Traitement synchrone des URL brutes http:// transformée en {lien web} ou {article}
 */

include __DIR__.'/../myBootstrap.php';

// todo VOIR EN BAS

/** @noinspection PhpUnhandledExceptionInspection */
$wiki = ServiceFactory::getMediawikiFactory();
$logger = new ConsoleLogger();
$logger->colorMode = true;
//$logger->debug = true;
$botConfig = new WikiBotConfig($wiki, $logger);
$botConfig->setTaskName("🐭 Amélioration de références : URL ⇒ "); // 🐞🌐🧅🔗

$botConfig->checkStopOnTalkpageOrException();

// LAST EDIT
// TODO : \<ref[^\>]*\> et liste à puces * http://...
$list = new CirrusSearch(
    [
        'srsearch' => '"http" insource:/\<ref[^\>]*\> ?https?\:\/\/[^\<\ ]+ *\<\/ref/',
        'srlimit' => '500',
        'srqiprofile' => CirrusSearch::SRQIPROFILE_DEFAULT,
        'srsort' => CirrusSearch::SRSORT_LAST_EDIT_DESC,
    ],
    // continue: false because last_edit_desc sorting
    [CirrusSearch::OPTION_REVERSE => true, CirrusSearch::OPTION_CONTINUE => false]
);
$titles = $list->getPageTitles();

// sroffset 500
sleep(3);
$list = new CirrusSearch(
    [
        'srsearch' => '"http" insource:/\<ref[^\>]*\> ?https?\:\/\/[^\<\ ]+ *\<\/ref/',
        'srlimit' => '500',
        'sroffset' => '500',
        'srqiprofile' => CirrusSearch::SRQIPROFILE_DEFAULT,
        'srsort' => CirrusSearch::SRSORT_LAST_EDIT_DESC,
    ],
    // continue: false because last_edit_desc sorting
    [CirrusSearch::OPTION_REVERSE => true, CirrusSearch::OPTION_CONTINUE => false]
);
$titles = array_merge($titles, $list->getPageTitles());

// sroffset 1000
sleep(3);
$list = new CirrusSearch(
    [
        'srsearch' => '"http" insource:/\<ref[^\>]*\> ?https?\:\/\/[^\<\ ]+ *\<\/ref/',
        'srlimit' => '500',
        'sroffset' => '1000',
        'srqiprofile' => CirrusSearch::SRQIPROFILE_DEFAULT,
        'srsort' => CirrusSearch::SRSORT_LAST_EDIT_DESC,
    ],
    // continue: false because last_edit_desc sorting
    [CirrusSearch::OPTION_REVERSE => true, CirrusSearch::OPTION_CONTINUE => false]
);
$titles = array_merge($titles, $list->getPageTitles());

// filter titles already in edited.txt
unset($list);
//echo count($titles)." titles\n";
$edited = file(__DIR__ . '/../resources/article_externRef_edited.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$filtered = array_diff($titles, $edited);
$list = new PageList($filtered);
echo ">" . $list->count() . " dans liste\n";


$httpClient = ServiceFactory::getHttpClient();
$wikiwix = new WikiwixAdapter($httpClient, $logger);
$internetArchive = new InternetArchiveAdapter($httpClient, $logger);

// 2nd pass without Tor when the Tor fetch looks blocked (403/429/503, cf-mitigated,
// interstitial body markers) — on by default, self-identifies honestly (not a fake
// browser UA) on that direct pass. --no-direct-retry opts back out entirely.
// See audits/synthese-anti-bot-crawling-tor-2026-08.md
$directRetryEnabled = !in_array('--no-direct-retry', $argv, true);

$domainParser = new InternetDomainParser();
$transformer = new ExternRefTransformer(
    new ExternMapper($logger),
    ServiceFactory::getHttpClient(true),
    $domainParser,
    $logger,
    [$internetArchive, $wikiwix],
    ServiceFactory::getExternLinkCheckRepository($argv),
    $directRetryEnabled ? $httpClient : null
);

$dryRun = in_array('--dry-run', $argv, true);
new ExternRefWorker($botConfig, $wiki, $list, $transformer, $dryRun);

echo "END of process\n";
