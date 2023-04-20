<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Domain\InfrastructurePorts;

/**
 * For classes generating wiki articles list.
 * Interface PageListInterface
 * Todo generator type ? adapter infra ?
 *
 * @package App\Infrastructure
 */
interface PageListInterface
{
    public function getPageTitles(): array;
}
