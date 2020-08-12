<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application;

interface QueueInterface
{
    public function getNewRaw(): ?array;

    public function sendCompletedData(array $finalData): bool;

    public function skipRow(int $id):bool;

}
