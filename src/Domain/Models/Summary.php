<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Domain\Models;

/**
 * See also EditSummaryTrait
 */
class Summary
{
    public $taskName;
    public $prefix = '';
    public $citationNumber = 0;
    public $botFlag = false;
    public $minorFlag = false;
    public $memo = [];

    /**
     * Summary constructor.
     *
     * @param $taskName
     */
    public function __construct(string $taskName)
    {
        $this->taskName = $taskName;
    }

    public function serializePrefixAndTaskname(): string
    {
        $prefixSummary = ($this->botFlag) ? 'bot: ' : '';

        return trim($prefixSummary.$this->taskName);
    }

    /**
     * @return bool
     */
    public function isBotFlag(): bool
    {
        return $this->botFlag;
    }

    /**
     * @param bool $botFlag
     */
    public function setBotFlag(bool $botFlag): void
    {
        $this->botFlag = $botFlag;
    }

    /**
     * @return bool
     */
    public function isMinorFlag(): bool
    {
        return $this->minorFlag;
    }

    /**
     * @param bool $minorFlag
     */
    public function setMinorFlag(bool $minorFlag): void
    {
        $this->minorFlag = $minorFlag;
    }

}
