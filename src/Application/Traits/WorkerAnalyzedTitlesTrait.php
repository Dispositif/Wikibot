<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\Traits;

use App\Domain\InfrastructurePorts\BotEditJournalInterface;
use App\Infrastructure\ServiceFactory;

trait WorkerAnalyzedTitlesTrait
{
    protected BotEditJournalInterface $botEditJournal;

    protected function initializePastAnalyzedTitles(): void
    {
        $this->botEditJournal = ServiceFactory::getBotEditJournal(static::ARTICLE_ANALYZED_FILENAME);
    }

    protected function memorizeAndSaveAnalyzedTitle(string $title): void
    {
        $this->botEditJournal->recordAnalyzed($title, static::JOURNAL_TASK);
    }

    protected function checkAlreadyAnalyzed(string $title): bool
    {
        return $this->botEditJournal->wasAnalyzed($title, static::JOURNAL_TASK);
    }

    protected function recordEditedTitle(string $title, ?int $revid = null): void
    {
        $this->botEditJournal->recordEdit($title, static::JOURNAL_TASK, $revid);
    }
}
