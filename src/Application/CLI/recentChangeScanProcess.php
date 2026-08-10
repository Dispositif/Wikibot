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
use App\Infrastructure\RecentChange\RecentChangeSignalAdapter;
use App\Infrastructure\ServiceFactory;
use DateInterval;
use DateTimeImmutable;

require_once __DIR__ . '/../myBootstrap.php';

/**
 * RC scanner/sampler. Exercises the cursor/source plumbing for real (rc_cursor
 * is genuinely read/advanced/persisted), and — by default — persists every event as-is
 * into rc_signal with signal_name='observed', so the accumulated data is queryable
 * afterward (page/user/tags/comment) for manual analysis and Lot 4 threshold
 * calibration. Not Lot 4 itself : no matcher, no filtering — everything gets the same
 * neutral signal. --no-signal reverts to pure counting (nothing written but rc_cursor).
 *
 * UNIQUE(revid, signal_name) makes this crash-safe : if the process dies mid-run,
 * already-inserted rows are skipped (INSERT IGNORE) on the next attempt, and since
 * rc_cursor is only advanced at the very end, a killed run simply gets retried from the
 * same start point — no gaps, no duplicates.
 *
 * Self-purges 'observed' rows older than SAMPLE_RETENTION_DAYS on every run, so a long
 * loop (`while true; do make rc-scan; sleep 60; done`) can't silently grow rc_signal
 * without bound — no need to remember a separate cleanup step. For an immediate wipe
 * instead of waiting out the retention window, see purgeRcSignal.php (`make rc-clean`).
 *
 * Single-shot batch, like goo-extern/fix-typo — not a loop. Run it on a schedule
 * (cron) once that's wired up for this project ; until then, loop it externally
 * (`while true; do make rc-scan; sleep 60; done`). No flock() here yet either — don't
 * run two instances concurrently against the same cursor, they'd race on rc_cursor.
 */

const SOURCE_NAME = 'mediawiki-rc';
const CURSOR_OVERLAP_SECONDS = 5;
const DEFAULT_LOOKBACK = 'PT10M'; // first-ever run, no cursor yet: start 10 minutes back
const SAMPLE_SIGNAL = 'observed';
const SAMPLE_RETENTION_DAYS = 2;

$persistSignals = !in_array('--no-signal', $argv, true);

$cursorRepository = new RecentChangeCursorAdapter(ServiceFactory::getMysqlConnection());
$signalRepository = new RecentChangeSignalAdapter(ServiceFactory::getMysqlConnection());
$source = new MediawikiRecentChangeSource(ServiceFactory::getMediawikiApi());

$lastKnown = $cursorRepository->get(SOURCE_NAME) ?? (new DateTimeImmutable())->sub(new DateInterval(DEFAULT_LOOKBACK));
$since = $lastKnown->sub(new DateInterval('PT' . CURSOR_OVERLAP_SECONDS . 'S'));

echo "*** RC scan — since {$since->format('c')} " . ($persistSignals ? "(persisting to rc_signal)" : "(--no-signal : counting only)") . " ***\n";

$count = 0;
$byNs = [];
$byUserKind = [];
$latestTimestamp = $since;

foreach ($source->stream(new RecentChangeCursor($since)) as $event) {
    $count++;
    $byNs[$event->ns] = ($byNs[$event->ns] ?? 0) + 1;
    $byUserKind[$event->userKind->value] = ($byUserKind[$event->userKind->value] ?? 0) + 1;
    if ($persistSignals) {
        $signalRepository->record($event, SAMPLE_SIGNAL);
    }
    if ($event->timestamp > $latestTimestamp) {
        $latestTimestamp = $event->timestamp;
    }
}

if ($count > 0) {
    $cursorRepository->set(SOURCE_NAME, $latestTimestamp);
}

$purged = $persistSignals
    ? $signalRepository->purgeOlderThan(SAMPLE_SIGNAL, new DateInterval('P' . SAMPLE_RETENTION_DAYS . 'D'))
    : 0;

echo "  events:      $count\n";
echo "  by ns:       " . json_encode($byNs) . "\n";
echo "  by userKind: " . json_encode($byUserKind) . "\n";
echo "  memory peak: " . round(memory_get_peak_usage(true) / 1024 / 1024, 1) . " MB\n";
echo "  cursor now:  {$latestTimestamp->format('c')}\n";
if ($purged > 0) {
    echo "  purged:      $purged '" . SAMPLE_SIGNAL . "' row(s) older than " . SAMPLE_RETENTION_DAYS . "d\n";
}
