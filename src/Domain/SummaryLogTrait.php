<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Domain;

/**
 * todo Refac : currently used by Application + Domain (move Domain?)
 * Trait SummaryLogTrait
 */
trait SummaryLogTrait
{
    protected $summaryLog = [];

    /**
     * @return array
     */
    public function getSummaryLog(): array
    {
        return $this->summaryLog;
    }

    public function resetSummaryLog(): void
    {
        $this->summaryLog = [];
    }

    /**
     * @param string $string
     */
    protected function addSummaryLog(string $string): void
    {
        if (!empty($string)) {
            $this->summaryLog[] = trim($string);
        }
    }
}
