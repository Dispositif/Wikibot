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

class Stats
{
    protected const DEFAULT_FILEPATH = __DIR__ . '/../../../log/stats.db';

    protected SQLite3 $db;

    public function __construct()
    {
        if (!class_exists('SQLite3')) {
            throw new Exception('Stats ERROR : SQLite 3 NOT supported.');
        }
        $this->db = new SQLite3(getenv('STATS_FILEPATH') ?: self::DEFAULT_FILEPATH);
        $this->createTableIfNotExists();
    }

    public function increment(string $key): bool
    {
        // upsert :)
        try {
            return $this->db->exec('INSERT INTO tagnum (tag) VALUES("' . $key . '") ON CONFLICT(tag) DO UPDATE SET num=num+1');
        } catch (Exception $e) {
            return false;
        }
    }

    public function set(string $key, int $num): bool
    {
        try {
            return $this->db->exec('INSERT OR REPLACE INTO tagnum (tag,num) VALUES("' . $key . '", ' . $num . ')');
        } catch (Exception $e) {
            return false;
        }
    }

    public function decrement(string $key): bool
    {
        try {
            return $this->db->exec('INSERT INTO tagnum (tag) VALUES("' . $key . '") ON CONFLICT(tag) DO UPDATE SET num=num-1');
        } catch (Exception $e) {
            return false;
        }
    }

    public function select(string $tag): ?int
    {
        try {
            $stmt = $this->db->prepare('SELECT tag,num FROM tagnum WHERE tag LIKE :tag');
            $stmt->bindValue(':tag', $tag, SQLITE3_TEXT);
            $result = $stmt->execute();

            return $result ? $result->fetchArray(SQLITE3_ASSOC)['num'] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    protected function createTableIfNotExists(): bool
    {
        try {
            return $this->db->exec("CREATE TABLE if not exists tagnum (
                tag VARCHAR(50) NOT NULL PRIMARY KEY,
                num int(11) NOT NULL DEFAULT 1
            )");
        } catch (Exception $e) {
            return false;
        }
    }
}