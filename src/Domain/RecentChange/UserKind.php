<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2026 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\RecentChange;

/**
 * Only what the ref-worthy-candidate matcher needs : distinguishing bot edits
 * from human ones. No Temp case : MediaWiki's temporary-account flag/format wasn't
 * confirmed reliable enough to classify, and the contributor-analysis feature that
 * would have needed it was dropped (2026-08 discussion). Add it only once a matcher
 * actually needs it, and after verifying the real API shape against live data.
 */
enum UserKind: string
{
    case Registered = 'registered';
    case Ip = 'ip';
    case Bot = 'bot';
}
