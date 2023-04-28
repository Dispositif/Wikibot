<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\InfrastructurePorts;

use App\Domain\Models\PageOuvrageDTO;

interface DbAdapterInterface
{
    public function getOptiValidDate(): string;

    public function getNewRaw(): ?PageOuvrageDTO;

    public function sendCompletedData(PageOuvrageDTO $pageOuvrage): bool;

    public function skipRow(int $id): bool;

    public function insertPageOuvrages(array $data);

    public function getAllRowsOfOneTitleToEdit(?int $limit = 100): ?string;

    public function skipArticle(string $title): bool;

    public function deleteArticle(string $title): bool;
}
