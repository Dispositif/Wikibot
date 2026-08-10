<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2026 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\RecentChange;

use DateTimeImmutable;

/**
 * Resume point for RecentChangeSourceInterface::stream() : events strictly after
 * $since. Timestamp only, no rcid tie-breaker — the caller is expected to apply a
 * small overlap (a few seconds) when computing $since from a persisted position, so a
 * resumed scan can't silently skip an event that shares a timestamp with the last one
 * seen. Safe to do : rc_signal's UNIQUE(revid, signal) (Lot 4) makes re-seeing the same
 * event harmless.
 */
final readonly class RecentChangeCursor
{
    public function __construct(public DateTimeImmutable $since)
    {
    }
}
