<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink\Raw\Hints;

/**
 * One recognizable marker in the "rest" text trailing a raw bracketed link (e.g.
 * ", sur Site" -> 'site'). RawExternLinkParser runs a fixed chain of these, one pattern
 * per class, matching the WikiOptimizer/Handlers convention used elsewhere in this
 * codebase for the same "chain of single-purpose recognizers" shape.
 */
interface HintExtractorInterface
{
    /**
     * @return HintMatch|null null when this extractor's pattern doesn't match $rest at
     *     all -- the parser then tries the next extractor in the chain unchanged.
     */
    public function extract(string $rest): ?HintMatch;
}
