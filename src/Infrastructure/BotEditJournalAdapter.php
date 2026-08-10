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
