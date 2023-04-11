<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application;

use DateTime;

/**
 * Used only by OuvrageEditWorker.
 */
trait OuvrageEditSummaryTrait
{
    /* Beware !! $importantSummary also defined in OuvrageEditWorker */
    public $importantSummary = [];

    /**
     * Generate wiki edition summary.
     */
    public function generateSummary(): string
    {
        $prefix = $this->generatePrefix();
        $citeSummary = $this->getCiteSummary();

        $summary = sprintf(
            '%s [%s] %s %sx : %s',
            trim($prefix),
            str_replace('v', '', $this->citationVersion),
            trim(self::TASK_NAME),
            $this->nbRows,
            $citeSummary
        );

        $summary = $this->shrinkLongSummaryIfNoImportantDetailsToVerify($summary);

        return $this->couldAddLuckMessage($summary);
    }

    /**
     * Shrink long summary if no important details to verify.
     */
    protected function shrinkLongSummaryIfNoImportantDetailsToVerify(string $summary): string
    {
        if (empty($this->importantSummary)) {
            $length = strlen($summary);
            $summary = mb_substr($summary, 0, 80);
            $summary .= ($length > strlen($summary)) ? '…' : '';
        } else {
            $summary .= '…'; // ?
        }
        return $summary;
    }

    protected function couldAddLuckMessage(string $summary): string
    {
        if (!$this->luckyState && (new DateTime())->format('H:i') === '11:11') {
            $this->luckyState = true;
            $summary .= self::LUCKY_MESSAGE;
        }

        return $summary;
    }

    protected function generatePrefix(): string
    {
        $prefix = ($this->botFlag) ? 'bot ' : '';
        $prefix .= (empty($this->errorWarning)) ? '' : ' ⚠️';
        $prefix .= (empty($this->featured_article)) ? '' : ' ☆'; // AdQ, BA

        return $prefix;
    }

    /**
     * Generate list of details about current bot edition.
     */
    protected function getCiteSummary(): string
    {
        // basic modifs
        $citeSummary = implode(' ', $this->citationSummary);
        // replaced by list of modifs to verify by humans
        if (!empty($this->importantSummary)) {
            $citeSummary = implode(', ', $this->importantSummary);
        }
        return $citeSummary;
    }

    /**
     * For substantive or ambiguous modifications done.
     *
     * @param string $tag
     */
    protected function addSummaryTag(string $tag)
    {
        if (!in_array($tag, $this->importantSummary)) {
            $this->importantSummary[] = $tag;
        }
    }
}
