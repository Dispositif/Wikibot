<?php

/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2026 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */
declare(strict_types=1);

namespace App\Application\CLI;

use App\Application\ExternLink\LienBriseArchiveWorker;
use App\Application\WikiBotConfig;
use App\Domain\ExternLink\LienBriseArchiveFixer;
use App\Infrastructure\CirrusSearch;
use App\Infrastructure\Monitor\ConsoleLogger;
use App\Infrastructure\PageList;
use App\Infrastructure\ServiceFactory;

/**
 * Mass campaign : finds pages carrying an existing {{Lien brisé}} (CirrusSearch) and
 * re-attempts a web archive (Internet Archive, then Wikiwix) on each one's url= via
 * LienBriseArchiveFixer. Edits "🏛️ Correction {lien brisé} → InternetArchive" (or
 * "🥝 ... Wikiwix" when that's what actually resolved), bot flag true.
 *
 * Usage:
 *   php lienBriseArchiveProcess.php [--max-titles=N] [--dry-run]
 */

include __DIR__ . '/../myBootstrap.php';

/** @noinspection PhpUnhandledExceptionInspection */
$wiki = ServiceFactory::getMediawikiFactory();
$logger = new ConsoleLogger();
$logger->colorMode = true;
$botConfig = new WikiBotConfig($wiki, $logger);
$botConfig->setTaskName('🏛️³ Correction de {lien brisé} : archive');
$botConfig->setMaxTitles(WikiBotConfig::maxTitlesFromArgv($argv));

$botConfig->checkStopOnTalkpageOrException();

// srsort=random, not last_edit_desc : same reasoning as existingRefProcess.php/
// lastExternRefProcess.php (see audits/audit-sources-listes-articles-2026-08.md) — a
// recency-sorted slice renews slower than the run cadence, so consecutive runs mostly
// re-serve titles the journal already saw.
$cirrusSearch = new CirrusSearch(
    [
        'srsearch' => 'hastemplate:"Lien brisé"',
        'srlimit' => CirrusSearch::SRLIMIT_MAX, // 5000 with the bot account's apihighlimits, 500 otherwise
        'srsort' => CirrusSearch::SRSORT_RANDOM,
    ],
    [CirrusSearch::OPTION_APILOGIN => true, CirrusSearch::OPTION_CONTINUE => false]
);

$candidates = $cirrusSearch->getPageTitles();
$titles = ServiceFactory::getBotEditJournal(LienBriseArchiveWorker::ARTICLE_ANALYZED_FILENAME)
    ->filterNotAnalyzed($candidates, LienBriseArchiveWorker::JOURNAL_TASK);

$list = new PageList($titles);
echo sprintf(">%d dans liste (%d tirés, %d déjà analysés)\n", $list->count(), count($candidates), count($candidates) - $list->count());

$fixer = new LienBriseArchiveFixer(ServiceFactory::getDeadLinkTransformer($logger));

$dryRun = in_array('--dry-run', $argv, true);
new LienBriseArchiveWorker($botConfig, $wiki, $list, $fixer, $dryRun);

echo "END of process\n";
