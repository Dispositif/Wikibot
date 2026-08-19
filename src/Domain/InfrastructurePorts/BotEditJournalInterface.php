<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\InfrastructurePorts;

use DateTimeImmutable;

/**
 * Bot's own analysis/edit journal — replaces the per-worker flat files
 * (article_edited.txt, article_externRef_edited.txt, gooBot_edited.txt) that were read
 * in full into memory and grew unbounded (~28k lines as of 2026-08).
 *
 * Two distinct writes, on purpose : "analyzed" is a per-(page, task) STATE (was this
 * page looked at, regardless of outcome — the skip-reprocessing guard consulted on
 * every title), "edited" is an append-only EVENT log (a page can be genuinely edited
 * several times over its life, and that history is exactly what a correction script
 * needs to select against — "every page the extern-ref pipeline actually touched").
 */
interface BotEditJournalInterface
{
    public function wasAnalyzed(string $page, string $task): bool;

    /**
     * Bulk counterpart of wasAnalyzed(), for sieving a whole candidate list before the
     * worker loop starts. Two reasons it is not just a loop over wasAnalyzed() : it
     * collapses thousands of round-trips into a handful of queries, and it lets a CLI
     * announce the real amount of work up front instead of a figure that only becomes
     * true once most titles have been skipped one by one.
     *
     * @param string[] $pages
     *
     * @return string[] the subset of $pages never analyzed for $task, in input order,
     *                  duplicates removed
     */
    public function filterNotAnalyzed(array $pages, string $task): array;

    public function recordAnalyzed(string $page, string $task): void;

    /** Un-marks (page, task) as analyzed — e.g. to force a worker to re-pass on it. */
    public function forgetAnalyzed(string $page, string $task): void;

    public function recordEdit(string $page, string $task, ?int $revid = null): void;

    /**
     * "every page the extern-ref pipeline actually touched" (see class docblock) — what a
     * correction script selects against : e.g. all pages a given task edited since a bug was
     * introduced, to replay/fix them instead of re-sweeping the whole of Wikipedia.
     *
     * @param string[] $tasks
     *
     * @return array<array{page: string, task: string, revid: ?int, edited_at: string}> one row
     *              per bot_edit entry (a page edited twice by the same task appears twice),
     *              most recent first
     */
    public function getEditedPages(array $tasks, ?DateTimeImmutable $since = null): array;
}
