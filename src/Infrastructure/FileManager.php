<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\InfrastructurePorts\PageListInterface;

class FileManager implements PageListInterface
{
    use CsvTrait;

    public function getPageTitles(): array
    {
        // TODO: Implement getPageTitles() method.
        return [];
    }
}
