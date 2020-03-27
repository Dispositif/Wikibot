<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);


namespace App\Infrastructure;

/**
 * For classes generating wiki articles list.
 * Interface PageListInterface
 *
 * @package App\Infrastructure
 */
interface PageListInterface
{
    public function getPageTitles(): array;
}
