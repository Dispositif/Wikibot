<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2026 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink;

use DateTimeInterface;

/**
 * Outcome of LiveLinkArchiveEnricher::enrich() : an archive candidate whose content
 * scored high enough to trust. Binary by design (2026-08-20) : either enrich() returns
 * one of these and the caller attaches archive-url/-date outright, or it returns null
 * and NOTHING is touched -- no in-between "flag for review" state, no wikitext comment.
 * A prior version added a middle "SemiAuto" tier that left an HTML comment on the
 * citation ; rejected outright (never asked for, and specifically not wanted -- an
 * archive suggestion has no business appearing in the article's rendered/source text
 * outside the {{lien web}}/{{article}} template itself).
 */
final class LiveArchiveMatch
{
    public function __construct(
        public readonly string $archiveUrl,
        // Wayback only : WikiwixAdapter always returns null since Wikiwix's SPA migration
        // (no snapshot-listing API left), see its own class docblock. Never fabricated here.
        public readonly ?DateTimeInterface $archiveDate,
        public readonly string $archiverName,
        public readonly float $score,
    ) {
    }
}
