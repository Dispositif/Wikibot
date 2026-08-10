<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2026 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\CLI;

use App\Infrastructure\RecentChange\RecentChangeSignalAdapter;
use App\Infrastructure\ServiceFactory;
use DateInterval;

require_once __DIR__ . '/../myBootstrap.php';

/**
 * Manual, on-demand cleanup of rc_signal's disposable 'observed' samples
 * (recentChangeScanProcess.php also self-purges rows older than its own
 * SAMPLE_RETENTION_DAYS on every run — this script is for "I'm done analyzing right
 * now, wipe it", instead of waiting out that window).
 *
 * --older-than-days=N : keep the last N days, purge the rest. Default 0 : purge
 * everything currently in rc_signal for signal='observed'.
 */

$days = 0;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--older-than-days=')) {
        $days = max(0, (int)substr($arg, strlen('--older-than-days=')));
    }
}

$signalRepository = new RecentChangeSignalAdapter(ServiceFactory::getMysqlConnection());
$purged = $signalRepository->purgeOlderThan('observed', new DateInterval('P' . $days . 'D'));

echo "Purged $purged 'observed' row(s) from rc_signal (older than {$days}d).\n";
