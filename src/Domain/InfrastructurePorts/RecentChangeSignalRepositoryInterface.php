<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2026 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\InfrastructurePorts;

use App\Domain\RecentChange\RecentChangeEvent;

/**
 * Writes to rc_signal (schema: database_schema.sql). Not Lot 4's matcher-driven
 * signals yet — the scanner (recentChangeScanProcess.php) currently only ever calls
 * this with signal='observed', persisting every event unfiltered so it can be queried
 * for analysis. UNIQUE(revid, signal) makes repeated calls for the same event
 * idempotent : safe to re-run after a crash, safe under the cursor's overlap window.
 */
interface RecentChangeSignalRepositoryInterface
{
    public function record(RecentChangeEvent $event, string $signal, int $weight = 1): void;
}
