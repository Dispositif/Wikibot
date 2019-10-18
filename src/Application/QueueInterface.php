<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 (c) Philippe M. <dispositif@gmail.com>
 * For the full copyright and license information, please view the LICENSE file
 */

declare(strict_types=1);

namespace App\Application;

interface QueueInterface
{
    public function getNewRaw(): ?string;

    public function sendCompletedData(array $finalData): bool;
}
