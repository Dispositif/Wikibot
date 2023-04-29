<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Domain\Models;

/**
 * Use by external link workers. Redundant with PageWorkStatus ?
 * See also EditSummaryTrait
 */
class Summary
{
    public $taskName;
    public $prefix = '';
    public $citationNumber = 0;
    public $botFlag = false;
    public $minorFlag = false;
    public $log = [];
    public $memo = [];

    public function __construct(string $taskName)
    {
        $this->taskName = $taskName;
    }

    public function serializePrefixAndTaskname(): string
    {
        $prefixSummary = ($this->botFlag) ? 'bot: ' : '';

        return trim($prefixSummary.$this->taskName);
    }

    public function isBotFlag(): bool
    {
        return $this->botFlag;
    }

    public function setBotFlag(bool $botFlag): void
    {
        $this->botFlag = $botFlag;
    }

    public function isMinorFlag(): bool
    {
        return $this->minorFlag;
    }

    public function setMinorFlag(bool $minorFlag): void
    {
        $this->minorFlag = $minorFlag;
    }

}
