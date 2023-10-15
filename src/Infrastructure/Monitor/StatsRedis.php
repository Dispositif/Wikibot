<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure\Monitor;

use Exception;
use Redis;
use RuntimeException;

class StatsRedis implements StatsInterface
{
    protected Redis $redis;

    public function __construct()
    {
        if (!class_exists('Redis')) {
            throw new Exception('Stats ERROR : Redis NOT supported.');
        }
        $redis = new Redis();
        $redis->connect(
            getenv('REDIS_HOST') ?: '127.0.0.1',
            (int) getenv('REDIS_PORT')
        );
        if (!$redis->isConnected()) {
            throw new RuntimeException('Redis not connected');
        }
        $this->redis = $redis;
    }

    public function increment(string $tag): bool
    {
        $this->redis->incr('monthly.'.date('Ym.').$tag);
        $this->redis->incr('daily.'.date('Ymd.').$tag);

        return $this->redis->incr('total.'.$tag) !== false;
    }
}