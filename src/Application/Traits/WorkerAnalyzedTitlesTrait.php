<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\Traits;

use Throwable;

/*
 * todo extract to service/adapter/infra
 */
trait WorkerAnalyzedTitlesTrait
{
    protected function initializePastAnalyzedTitles(): void
    {
        try {
            $analyzed = file(
                static::ARTICLE_ANALYZED_FILENAME,
                FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
            );
        } catch (Throwable $e) {
            $this->log->critical("Can't parse ARTICLE_ANALYZED_FILENAME : " . $e->getMessage());
            $analyzed = [];
        }
        $this->pastAnalyzed = ($analyzed !== false) ? $analyzed : [];
    }

    protected function memorizeAndSaveAnalyzedTitle(string $title): void
    {
        if (!$this->checkAlreadyAnalyzed($title)) {
            $this->pastAnalyzed[] = $title; // skip doublon title
            @file_put_contents(static::ARTICLE_ANALYZED_FILENAME, $title . PHP_EOL, FILE_APPEND);
        }
    }

    protected function checkAlreadyAnalyzed(string $title): bool
    {
        return is_array($this->pastAnalyzed) && in_array($title, $this->pastAnalyzed);
    }
}