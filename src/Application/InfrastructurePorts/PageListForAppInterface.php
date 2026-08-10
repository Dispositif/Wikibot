<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\InfrastructurePorts;

interface PageListForAppInterface
{
    public function getPageTitles(): array;

    /**
     * Same titles as getPageTitles(), but without materializing them all in memory first
     * — implementations backed by a paginated source (search API...) should yield page by
     * page instead of merging everything upfront.
     *
     * @return iterable<string>
     */
    public function stream(): iterable;
}