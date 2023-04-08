<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application;

use DateTime;

trait EditSummaryTrait
{
    // Beware !! $importantSummary also defined in Application/OuvrageEditWorker.php
    public $importantSummary = [];

    /**
     * For substantive or ambiguous modifications done.
     *
     * @param string $tag
     */
    private function addSummaryTag(string $tag)
    {
        if (!in_array($tag, $this->importantSummary)) {
            $this->importantSummary[] = $tag;
        }
    }

    /**
     * todo extract
     * Generate wiki edition summary.
     * @return string
     */
    public function generateSummary(): string
    {
        // Start summary with "WikiBotConfig" when using botFlag, else "*"
        $prefix = ($this->botFlag) ? 'bot ' : ''; //🧐 🤖
        // add "/!\" when errorWarning
        $prefix .= (empty($this->errorWarning)) ? '' : ' ⚠️';
        $prefix .= (empty($this->featured_article)) ? '' : ' ☆'; // AdQ, BA

        // basic modifs
        $citeSummary = implode(' ', $this->citationSummary);
        // replace by list of modifs to verify by humans
        if (!empty($this->importantSummary)) {
            $citeSummary = implode(', ', $this->importantSummary);
        }

        $summary = sprintf(
            '%s [%s] %s %sx : %s',
            trim($prefix),
            str_replace('v', '', $this->citationVersion),
            trim(self::TASK_NAME),
            $this->nbRows,
            $citeSummary
        );

        if (!empty($this->importantSummary)) {
            $summary .= '...';
        }

        // shrink long summary if no important details to verify
        if (empty($this->importantSummary)) {
            $length = strlen($summary);
            $summary = mb_substr($summary, 0, 80);
            $summary .= ($length > strlen($summary)) ? '…' : '';
        }

        // Luck message
        if (!$this->luckyState && (new DateTime())->format('H:i') === '11:11') {
            $this->luckyState = true;
            $summary .= self::LUCKY_MESSAGE;
        }

        return $summary;
    }

}
