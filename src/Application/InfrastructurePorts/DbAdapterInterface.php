<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\InfrastructurePorts;

interface DbAdapterInterface
{
    public function getOptiValidDate(): string;

    public function getNewRaw(): ?array;

    public function sendCompletedData(array $finalData): bool;

    public function skipRow(int $id): bool;

    public function insertPageOuvrages(array $data);
}
