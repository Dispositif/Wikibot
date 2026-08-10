<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2026 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure\RecentChange;

use App\Domain\InfrastructurePorts\RecentChangeSignalRepositoryInterface;
use App\Domain\RecentChange\RecentChangeEvent;
use DateTimeImmutable;
use DateTimeZone;
use Simplon\Mysql\Mysql;

/**
 * MySQL-backed RecentChangeSignalRepositoryInterface (schema:
 * src/Infrastructure/resources/database_schema.sql, table rc_signal).
 */
class RecentChangeSignalAdapter implements RecentChangeSignalRepositoryInterface
{
    private const TABLE = 'rc_signal';

    public function __construct(private readonly Mysql $db)
    {
    }

    public function record(RecentChangeEvent $event, string $signal, int $weight = 1): void
    {
        $this->db->insert(
            self::TABLE,
            [
                'revid' => $event->revid,
                'old_revid' => $event->oldRevid,
                'page' => $event->page,
                'ns' => $event->ns,
                'user' => $event->user,
                'user_kind' => $event->userKind->value,
                'rc_timestamp' => $event->timestamp->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                'size_diff' => $event->sizeDiff,
                'comment' => $event->comment !== null ? mb_substr($event->comment, 0, 500) : null,
                'tags' => !empty($event->tags) ? mb_substr(implode(',', $event->tags), 0, 255) : null,
                'signal' => $signal,
                'weight' => $weight,
                'state' => 'new',
                'detected_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
            ],
            true // insertIgnore : idempotent on UNIQUE(revid, signal)
        );
    }
}
