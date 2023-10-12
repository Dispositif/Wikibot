<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure\Monitor;

use Psr\Log\NullLogger as PsrNullLogger;

// todo replace everywhere
class NullLogger extends PsrNullLogger
{
    public function __construct()
    {
        $this->stats = new NullStats();
    }
}