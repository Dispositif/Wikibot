<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 (c) Philippe M. <dispositif@gmail.com>
 * For the full copyright and license information, please view the LICENSE file
 */

declare(strict_types=1);

namespace App\Domain\Publisher;

interface BookApiInterface
{
    public function getDataByIsbn(string $isbn);

    public function getMapper();
}
