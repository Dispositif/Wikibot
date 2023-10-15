<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure\Monitor;

use Exception;
use SQLite3;

/**
 * SQLite3 base: stats.db
 * tables: tagnum, tagnum_daily, tagnum_monthly
 */
class Stats
{
    protected const DEFAULT_FILEPATH = __DIR__ . '/../../../log/stats.db';
    protected const MAX_TAG_LENGTH = 100;

    protected SQLite3 $db;

    public function __construct()
    {
        $this->createTableIfNotExists();
    }

    public function increment(string $tag): bool
    {
        $tag = $this->formatTag($tag);

        $this->upsertTag(sprintf('%s.%s', date('Ymd'), $tag), 'tagnum_daily');
        $this->upsertTag(sprintf('%s.%s', date('Ym'), $tag), 'tagnum_monthly');

        return $this->upsertTag($tag);
    }

    private function formatTag(string $tag)
    {
        return mb_substr($tag, 0, self::MAX_TAG_LENGTH);
    }

    protected function upsertTag(string $tag, string $table = 'tagnum'): bool
    {
        // `num` default value is 1 so insert is enough to set num=1
        try {
            return $this->sqliteExecWriteOrWait(
                'INSERT INTO ' . $table . ' (tag) VALUES("' . $tag . '") ON CONFLICT(tag) DO UPDATE SET num=num+1'
            );
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Sqlite do not allow concurrent write from different process.
     * So wait a little and retry.
     */
    protected function sqliteExecWriteOrWait(string $query, int $maxRetry = 10): bool
    {
        $this->open();
        $retry = 0;
        while ($retry < $maxRetry) {
            $success = @$this->db->exec($query);
            if ($success) {
                $this->close();
                return true;
            }
            $retry++;
            usleep(100000); // 100000 µs = 0.1 s
        }
        echo "DEBUG: Sqlite retry : max retry reached\n"; // todo remove
        $this->close();

        return false;
    }

    public function set(string $tag, int $num): bool
    {
        $tag = $this->formatTag($tag);
        try {
            return $this->sqliteExecWriteOrWait(
                'INSERT OR REPLACE INTO tagnum (tag,num) VALUES("' . $tag . '", ' . $num . ')'
            );
        } catch (Exception $e) {
            return false;
        }
    }

    public function decrement(string $tag): bool
    {
        $tag = $this->formatTag($tag);
        try {
            return $this->sqliteExecWriteOrWait(
                'INSERT INTO tagnum (tag) VALUES("' . $tag . '") ON CONFLICT(tag) DO UPDATE SET num=num-1'
            );
        } catch (Exception $e) {
            return false;
        }
    }

    public function select(string $tag): ?int
    {
        $tag = $this->formatTag($tag);
        $this->open();
        try {
            $stmt = $this->db->prepare('SELECT tag,num FROM tagnum WHERE tag LIKE :tag');
            $stmt->bindValue(':tag', $tag, SQLITE3_TEXT);
            $result = $stmt->execute();
            $this->close();

            return $result ? $result->fetchArray(SQLITE3_ASSOC)['num'] : null;
        } catch (Exception $e) {
            $this->close();
            return null;
        }
    }

    protected function open(): void
    {
        if (!class_exists('SQLite3')) {
            throw new Exception('Stats ERROR : SQLite 3 NOT supported.');
        }
        $this->db = new SQLite3(getenv('STATS_FILEPATH') ?: self::DEFAULT_FILEPATH);
    }

    protected function close()
    {
        $this->db->close();
    }

    protected function createTableIfNotExists(): bool
    {
        $this->open();
        // note : Sqlite VARCHAR(X) do not truncate to the supposed X max chars. VARCHAR is TEXT.
        try {
            $this->db->exec('CREATE TABLE if not exists tagnum_monthly (
                tag TEXT NOT NULL PRIMARY KEY,
                num int(11) NOT NULL DEFAULT 1
            )');

            $this->db->exec('CREATE TABLE if not exists tagnum_daily (
                tag TEXT NOT NULL PRIMARY KEY,
                num int(11) NOT NULL DEFAULT 1
            )');

            $res = $this->db->exec('CREATE TABLE if not exists tagnum (
                tag TEXT NOT NULL PRIMARY KEY,
                num int(11) NOT NULL DEFAULT 1
            )');

            $this->close();

            return $res;
        } catch (Exception $e) {
            $this->close();
            return false;
        }
    }
}