<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure\Monitor;

class NullStats implements StatsInterface
{
    public function increment(string $tag): bool
    {
        return false;
    }

    public function __call(string $method, array $args)
    {
        return false;
    }
}