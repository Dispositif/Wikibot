<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);


namespace App\Domain;

/**
 * Refac to inject class ?
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
