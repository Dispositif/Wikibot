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
use Simplon\Mysql\Mysql;

/**
 * MySQL-backed BotEditJournalInterface, two tables (schema:
 * src/Infrastructure/resources/database_schema.sql) :
 *  - `bot_page_analyzed` : state, one row per (page, task).
 *  - `bot_edit` : append-only journal, one row per actual edit.
 */
class BotEditJournalAdapter implements BotEditJournalInterface
{
    private const TABLE_ANALYZED = 'bot_page_analyzed';
    private const TABLE_EDIT = 'bot_edit';
    /** Keeps the expanded IN(...) placeholder list well under MySQL's statement limits. */
    private const ANALYZED_LOOKUP_CHUNK = 1000;

    public function __construct(private readonly Mysql $db)
    {
    }

    public function wasAnalyzed(string $page, string $task): bool
    {
        $row = $this->db->fetchColumn(
            'SELECT 1 FROM ' . self::TABLE_ANALYZED . ' WHERE page = :page AND task = :task',
            ['page' => $page, 'task' => $task]
        );

        return $row !== null;
    }

    public function filterNotAnalyzed(array $pages, string $task): array
    {
        $pages = array_values(array_unique($pages));
        if ($pages === []) {
            return [];
        }

        $analyzed = [];
        foreach (array_chunk($pages, self::ANALYZED_LOOKUP_CHUNK) as $chunk) {
            // fetchRowMany and not fetchColumnMany : the latter loops on
            // `while ($v = fetchColumn())`, so it would stop dead on a falsy value —
            // and "0" is a real article title on fr.wikipedia.
            $rows = $this->db->fetchRowMany(
                'SELECT page FROM ' . self::TABLE_ANALYZED . ' WHERE task = :task AND page IN (:pages)',
                ['task' => $task, 'pages' => $chunk]
            );
            foreach ($rows ?? [] as $row) {
                $analyzed[(string)$row['page']] = true;
            }
        }

        return array_values(
            array_filter($pages, static fn(string $page): bool => !isset($analyzed[$page]))
        );
    }

    public function recordAnalyzed(string $page, string $task): void
    {
        // insertIgnore : idempotent on the (page, task) primary key, safe under races
        // between concurrent workers checking/writing the same title.
        $this->db->insert(
            self::TABLE_ANALYZED,
            [
                'page' => $page,
                'task' => $task,
                'analyzed_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ],
            true
        );
    }

    public function forgetAnalyzed(string $page, string $task): void
    {
        $this->db->delete(self::TABLE_ANALYZED, ['page' => $page, 'task' => $task]);
    }

    public function recordEdit(string $page, string $task, ?int $revid = null): void
    {
        $this->db->insert(
            self::TABLE_EDIT,
            [
                'page' => $page,
                'task' => $task,
                'revid' => $revid,
                'edited_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]
        );
    }
}
