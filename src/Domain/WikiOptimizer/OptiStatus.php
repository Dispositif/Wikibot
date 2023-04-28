<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Domain\WikiOptimizer;


class OptiStatus
{
    protected $summary = [];
    protected $major = false;
    protected $notCosmetic = false;

    public function getSummary(): array
    {
        return $this->summary;
    }

    public function addSummaryLog(string $str): OptiStatus
    {
        $this->summary[] = $str;
        return $this;
    }

    public function isMajor(): bool
    {
        return $this->major;
    }

    public function setMajor(bool $major): OptiStatus
    {
        $this->major = $major;
        return $this;
    }

    public function isNotCosmetic(): bool
    {
        return $this->notCosmetic;
    }

    public function setNotCosmetic(bool $notCosmetic): OptiStatus
    {
        $this->notCosmetic = $notCosmetic;
        return $this;
    }
}