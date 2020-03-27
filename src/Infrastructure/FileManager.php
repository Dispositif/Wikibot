<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

class FileManager implements PageListInterface
{
    use CsvTrait;

    public function getPageTitles(): array
    {
        // TODO: Implement getPageTitles() method.
    }
}
