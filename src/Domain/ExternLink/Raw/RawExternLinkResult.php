<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink\Raw;

/**
 * Result of RawExternLinkTransformer::process() : the wikitext to use in place of the
 * original fragment (identical to the input when nothing could be done) and a
 * confidence verdict the caller (a worker) can act on -- Skip means "don't even offer
 * this as a diff", mirroring MergeConfidence's meaning but also covering cases
 * HintMerger never sees (unparsed fragment, failed/skipped crawl).
 */
final class RawExternLinkResult
{
    public function __construct(
        public readonly string $wikitext,
        public readonly MergeConfidence $confidence,
    ) {
    }
}
