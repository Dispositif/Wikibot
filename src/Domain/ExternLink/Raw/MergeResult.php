<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink\Raw;

/**
 * Result of HintMerger::merge() : the merged template param set (same shape as
 * ExternMapper::process()'s output -- 'titre', 'site', 'date', etc., ready to feed
 * ExternRefTransformer's existing chooseTemplateNameByData()/replaceSomeData() chain
 * unchanged), a confidence verdict, and the field-level conflicts that drove it -- kept
 * around for a semi-auto review UI or an edit-summary note, not just internal reasoning.
 */
final class MergeResult
{
    /**
     * @param array<string, string> $mapData
     * @param array<string, array{manuscript: string, crawled: string}> $conflicts
     */
    public function __construct(
        public readonly array $mapData,
        public readonly MergeConfidence $confidence,
        public readonly array $conflicts = [],
    ) {
    }
}
