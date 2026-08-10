<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2026 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure\RecentChange;

use App\Domain\InfrastructurePorts\RecentChangeCursorRepositoryInterface;
use DateTimeImmutable;
use DateTimeZone;
use Simplon\Mysql\Mysql;

/**
 * MySQL-backed RecentChangeCursorRepositoryInterface (schema:
 * src/Infrastructure/resources/database_schema.sql, table rc_cursor).
 *
 * DATETIME columns are timezone-naive : always convert to UTC before formatting for
 * storage, and always parse the stored string back as UTC explicitly — otherwise
 * DateTimeImmutable falls back to the script's default timezone (Europe/Paris, set in
 * myBootstrap.php), silently shifting the cursor by the UTC offset on every read.
 * Caught live : a second dry-run scan rescanned ~2h of history it shouldn't have.
 */
class RecentChangeCursorAdapter implements RecentChangeCursorRepositoryInterface
{
    private const TABLE = 'rc_cursor';

    public function __construct(private readonly Mysql $db)
    {
    }

    public function get(string $source): ?DateTimeImmutable
    {
        $row = $this->db->fetchColumn(
            'SELECT last_timestamp FROM ' . self::TABLE . ' WHERE source = :source',
            ['source' => $source]
        );

        return $row !== null ? new DateTimeImmutable($row, new DateTimeZone('UTC')) : null;
    }

    public function set(string $source, DateTimeImmutable $timestamp): void
    {
        $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $data = [
            'source' => $source,
            'last_timestamp' => $timestamp->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'updated_at' => $now,
        ];

        $exists = $this->db->fetchColumn(
            'SELECT 1 FROM ' . self::TABLE . ' WHERE source = :source',
            ['source' => $source]
        );

        if ($exists !== null) {
            $this->db->update(self::TABLE, ['source' => $source], $data);
        } else {
            $this->db->insert(self::TABLE, $data);
        }
    }
}
