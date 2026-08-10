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
 * One MediaWiki recentchanges row, translated to a stable shape independent of the
 * source (list=recentchanges today, EventStreams later — see RecentChangeSourceInterface).
 * Metadata only : no page content, no diff. Matchers (Lot 4) that need more descend to a
 * separate enrichment step, deliberately kept out of this cheap/high-volume socle.
 */
final readonly class RecentChangeEvent
{
    /**
     * @param string[] $tags
     */
    public function __construct(
        public int $revid,
        public ?int $oldRevid,
        public string $page,
        public int $ns,
        public string $user,
        public UserKind $userKind,
        public DateTimeImmutable $timestamp,
        public ?int $sizeDiff,
        public ?string $comment,
        public array $tags,
    ) {
    }
}
