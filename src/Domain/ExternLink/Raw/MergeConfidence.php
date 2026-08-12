<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink\Raw;

/**
 * Output of HintMerger::merge() : whether the merged param set is safe to publish
 * automatically, needs a human look, or shouldn't be turned into a citation at all.
 * See docs/situation-projet-WP-liens-externes.md and the "zero data loss" invariant
 * discussed in the feature's original plan -- an unconsumed manuscript residue or a
 * manuscript/crawl mismatch on a load-bearing field (site) drops confidence to
 * SemiAuto rather than silently picking one side.
 */
enum MergeConfidence
{
    case Auto;
    case SemiAuto;
    case Skip;
}
