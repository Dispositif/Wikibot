<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2026 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\InfrastructurePorts;

use App\Domain\RecentChange\RecentChangeCursor;
use App\Domain\RecentChange\RecentChangeEvent;

interface RecentChangeSourceInterface
{
    /**
     * Events strictly after $cursor->since, oldest first. Implementations paginate
     * internally and yield as they go — never materialize the full result set (see
     * Lot 0 / CirrusSearch::stream() for why : this is the higher-volume feed of the two).
     *
     * @return iterable<RecentChangeEvent>
     */
    public function stream(RecentChangeCursor $cursor): iterable;
}
