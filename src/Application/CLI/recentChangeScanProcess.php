<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2026 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\CLI;

use App\Domain\RecentChange\RecentChangeCursor;
use App\Infrastructure\RecentChange\MediawikiRecentChangeSource;
use App\Infrastructure\RecentChange\RecentChangeCursorAdapter;
use App\Infrastructure\ServiceFactory;
use DateInterval;
use DateTimeImmutable;

require_once __DIR__ . '/../myBootstrap.php';

/**
 * Lot 3 : dry-run RC scanner. Exercises the cursor/source plumbing for real (rc_cursor
 * is genuinely read/advanced/persisted), but detects nothing and writes no rc_signal —
 * there's no matcher yet to produce one (Lot 4). Purpose : run this repeatedly over
 * ~24h and read its own numbers (event count, memory peak, ns/userKind breakdown) to
 * calibrate Lot 4's thresholds on real throughput instead of estimates.
 *
 * Single-shot batch, like goo-extern/fix-typo — not a loop. Run it on a schedule
 * (cron) once that's wired up for this project ; until then, run it by hand a few
 * times spread over the day. No flock() here yet either — don't run two instances
 * concurrently against the same cursor, they'd race on rc_cursor.
 */

const SOURCE_NAME = 'mediawiki-rc';
const CURSOR_OVERLAP_SECONDS = 5;
const DEFAULT_LOOKBACK = 'PT10M'; // first-ever run, no cursor yet: start 10 minutes back

$cursorRepository = new RecentChangeCursorAdapter(ServiceFactory::getMysqlConnection());
$source = new MediawikiRecentChangeSource(ServiceFactory::getMediawikiApi());

$lastKnown = $cursorRepository->get(SOURCE_NAME) ?? (new DateTimeImmutable())->sub(new DateInterval(DEFAULT_LOOKBACK));
$since = $lastKnown->sub(new DateInterval('PT' . CURSOR_OVERLAP_SECONDS . 'S'));

echo "*** RC dry-run scan — since {$since->format('c')} ***\n";

$count = 0;
$byNs = [];
$byUserKind = [];
$latestTimestamp = $since;

foreach ($source->stream(new RecentChangeCursor($since)) as $event) {
    $count++;
    $byNs[$event->ns] = ($byNs[$event->ns] ?? 0) + 1;
    $byUserKind[$event->userKind->value] = ($byUserKind[$event->userKind->value] ?? 0) + 1;
    if ($event->timestamp > $latestTimestamp) {
        $latestTimestamp = $event->timestamp;
    }
}

if ($count > 0) {
    $cursorRepository->set(SOURCE_NAME, $latestTimestamp);
}

echo "  events:      $count\n";
echo "  by ns:       " . json_encode($byNs) . "\n";
echo "  by userKind: " . json_encode($byUserKind) . "\n";
echo "  memory peak: " . round(memory_get_peak_usage(true) / 1024 / 1024, 1) . " MB\n";
echo "  cursor now:  {$latestTimestamp->format('c')}\n";
