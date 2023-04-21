<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Models;

use Iterator;

class PageOuvrageCollectionDTO implements Iterator
{
    private $items = [];
    private $pointer = 0;

    public function __construct(array $items)
    {
        $this->items = array_values($items);
    }

    public function current(): PageOuvrageDTO
    {
        return $this->items[$this->pointer];
    }

    public function key(): int
    {
        return $this->pointer;
    }

    public function next(): void
    {
        $this->pointer++;
    }

    public function rewind(): void
    {
        $this->pointer = 0;
    }

    public function valid(): bool
    {
        return $this->pointer < count($this->items);
    }
}