<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink\Existing;

use App\Domain\ExternLink\Raw\MergeConfidence;

/**
 * Result of ExistingRefTransformer::process() : the ref content to use in place of the
 * original fragment (identical to the input when nothing could be done) and a
 * confidence verdict the caller (a worker) can act on. Reuses MergeConfidence from the
 * Raw namespace -- same three-way meaning (Auto/SemiAuto/Skip), no need for a second
 * identical enum.
 */
final class ExistingRefResult
{
    public function __construct(
        public readonly string $refContent,
        public readonly MergeConfidence $confidence,
    ) {
    }
}
