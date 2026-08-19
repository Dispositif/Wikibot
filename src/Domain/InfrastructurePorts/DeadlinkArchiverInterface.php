<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\InfrastructurePorts;

use App\Domain\Models\WebarchiveDTO;
use DateTimeInterface;

/**
 * Wikiwix, archive.org, etc.
 */
interface DeadlinkArchiverInterface
{
    public function searchWebarchive(string $url, ?DateTimeInterface $date = null): ?WebarchiveDTO;

    /**
     * Ranked candidate snapshots (best/closest-to-$date first), so a caller can move
     * on to the next one when a candidate turns out to be unusable (blank page,
     * parked domain...) instead of accepting the first result unconditionally.
     * Archivers without a listing API (e.g. Wikiwix) may just wrap searchWebarchive().
     *
     * $before : hard upper bound (candidates strictly at-or-before this date only), for
     * a caller that already tried the closest-to-$date neighborhood and got only unusable
     * snapshots back (soft-404/parking) -- typically because the domain was cybersquatted
     * sometime after $date, so anything AFTER $date is suspect by construction and worth
     * excluding outright rather than just ranked lower. Archivers with no listing API
     * (Wikiwix keeps one snapshot per URL) accept but ignore it, same as $date.
     *
     * @return WebarchiveDTO[]
     */
    public function searchWebarchiveCandidates(string $url, ?DateTimeInterface $date = null, int $limit = 5, ?DateTimeInterface $before = null): array;
}