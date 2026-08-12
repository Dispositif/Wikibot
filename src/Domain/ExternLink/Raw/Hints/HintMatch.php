<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink\Raw\Hints;

/**
 * Result of one HintExtractorInterface::extract() call : a predicted template param
 * (e.g. 'site', 'date', 'consulté le') plus whatever of $rest is left once that span is
 * consumed, so the next extractor in the chain works on a progressively shrinking string.
 */
final class HintMatch
{
    public function __construct(
        public readonly string $param,
        public readonly string $value,
        public readonly string $remaining,
    ) {
    }
}
