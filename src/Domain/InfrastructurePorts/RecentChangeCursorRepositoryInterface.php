<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2026 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\InfrastructurePorts;

use DateTimeImmutable;

/**
 * Persisted resume position, one row per source (rc_cursor — see database_schema.sql).
 * No file-based fallback/kill-switch here unlike BotEditJournalInterface : there's no
 * pre-existing flat-file behavior to preserve, this scanner is new.
 */
interface RecentChangeCursorRepositoryInterface
{
    public function get(string $source): ?DateTimeImmutable;

    public function set(string $source, DateTimeImmutable $timestamp): void;
}
