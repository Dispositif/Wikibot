<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\OuvrageEdit;

use DateTime;

/**
 * Used only by OuvrageEditWorker.
 * TODO make it used by PageWorkStatus instead of OuvrageEditWorker ? Or ImportantSummaryCreator ?
 */
trait OuvrageEditSummaryTrait
{
    /**
     * Generate wiki edition summary.
     */
    protected function generateFinalSummary(): string
    {
        $prefix = $this->generatePrefix();
        $citeSummary = $this->getCiteSummary();

        $summary = sprintf(
            '%s [%s] %s %sx : %s',
            trim((string) $prefix),
            str_replace('v', '', (string) $this->pageWorkStatus->citationVersion),
            trim((string) self::TASK_NAME),
            $this->pageWorkStatus->nbRows,
            $citeSummary
        );

        $summary = $this->shrinkLongSummaryIfNoImportantDetailsToVerify($summary);
//        $summary = $this->couldAddLuckMessage($summary);
        $this->log->notice($summary);

        return $summary;
    }

    /**
     * Shrink long summary if no important details to verify.
     */
    protected function shrinkLongSummaryIfNoImportantDetailsToVerify(string $summary): string
    {
        if (empty($this->pageWorkStatus->importantSummary)) {
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
        if (!$this->pageWorkStatus->luckyState && (new DateTime())->format('H:i') === '11:11') {
            $this->pageWorkStatus->luckyState = true;
            $summary .= self::LUCKY_MESSAGE;
        }

        return $summary;
    }

    protected function generatePrefix(): string
    {
        $prefix = ($this->pageWorkStatus->botFlag) ? 'bot ' : '';
        $prefix .= (empty($this->pageWorkStatus->errorWarning)) ? '' : ' ⚠️'; // AdQ, BA

        return $prefix . ((empty($this->pageWorkStatus->featured_article)) ? '' : ' ☆');
    }

    /**
     * Generate list of details about current bot edition.
     */
    protected function getCiteSummary(): string
    {
        // basic modifs
        $citeSummary = implode(' ', $this->pageWorkStatus->citationSummary);
        // replaced by list of modifs to verify by humans
        if (!empty($this->pageWorkStatus->importantSummary)) {
            $citeSummary = implode(', ', $this->pageWorkStatus->importantSummary);
        }
        return $citeSummary;
    }
}
