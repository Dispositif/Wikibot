<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\InfrastructurePorts\BotEditJournalInterface;
use DateTimeImmutable;

/**
 * File-backed BotEditJournalInterface — same flat-file scheme the workers already used
 * (one title per line, FILE_APPEND). Kept as the no-DB fallback (ServiceFactory
 * kill-switch) and to preserve the pre-migration analyzed-titles history that already
 * lives in $analyzedFilePath (e.g. article_externRef_edited.txt, ~28k lines).
 *
 * One instance is bound to one task's files (mirrors the historical 1-file-per-worker
 * layout) — unlike BotEditJournalAdapter, the $task argument on each method is not
 * consulted here, only kept for interface parity.
 *
 * The "edited" event log has no prior file-based history — it's new, purely additive.
 */
class FileBotEditJournal implements BotEditJournalInterface
{
    /** @var array<string, true>|null lazy-loaded, keyed by title for O(1) lookup */
    private ?array $analyzed = null;

    public function __construct(
        private readonly string $analyzedFilePath,
        private readonly string $editionsFilePath,
    )
    {
    }

    public function wasAnalyzed(string $page, string $task): bool
    {
        return isset($this->loadAnalyzed()[$page]);
    }

    public function filterNotAnalyzed(array $pages, string $task): array
    {
        $analyzed = $this->loadAnalyzed();

        return array_values(
            array_filter(
                array_unique($pages),
                static fn(string $page): bool => !isset($analyzed[$page])
            )
        );
    }

    public function recordAnalyzed(string $page, string $task): void
    {
        if ($this->wasAnalyzed($page, $task)) {
            return;
        }
        $this->analyzed[$page] = true;
        @file_put_contents($this->analyzedFilePath, $page . PHP_EOL, FILE_APPEND);
    }

    public function forgetAnalyzed(string $page, string $task): void
    {
        $text = @file_get_contents($this->analyzedFilePath);
        if ($text === false) {
            return;
        }
        $newText = str_replace($page . "\n", '', $text);
        if ($newText !== $text) {
            @file_put_contents($this->analyzedFilePath, $newText);
        }
        unset($this->analyzed[$page]);
    }

    public function recordEdit(string $page, string $task, ?int $revid = null): void
    {
        $line = sprintf(
            '%s | %s | %s',
            (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            $task,
            $page
        );
        @file_put_contents($this->editionsFilePath, $line . PHP_EOL, FILE_APPEND);
    }

    /**
     * Only ever sees this instance's own task/file (see class docblock) : $tasks values
     * other than the one this instance was constructed for are silently absent from the
     * result, same "not really multi-task" limitation as every other method here.
     */
    public function getEditedPages(array $tasks, ?DateTimeImmutable $since = null): array
    {
        $lines = @file($this->editionsFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $rows = [];
        foreach ($lines as $line) {
            // '%s | %s | %s' from recordEdit() above : date | task | page
            $parts = array_map('trim', explode('|', $line, 3));
            if (count($parts) !== 3 || !in_array($parts[1], $tasks, true)) {
                continue;
            }
            if (null !== $since && $parts[0] < $since->format('Y-m-d H:i:s')) {
                continue;
            }
            $rows[] = ['page' => $parts[2], 'task' => $parts[1], 'revid' => null, 'edited_at' => $parts[0]];
        }

        usort($rows, static fn(array $a, array $b): int => $b['edited_at'] <=> $a['edited_at']);

        return $rows;
    }

    /** @return array<string, true> */
    private function loadAnalyzed(): array
    {
        if ($this->analyzed === null) {
            $lines = @file($this->analyzedFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $this->analyzed = $lines !== false ? array_flip($lines) : [];
        }

        return $this->analyzed;
    }
}
