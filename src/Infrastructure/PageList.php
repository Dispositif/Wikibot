<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);


namespace App\Infrastructure;


class PageList implements PageListInterface
{
    protected $titles;

    /**
     * PageList constructor.
     *
     * @param $titles
     */
    public function __construct(array $titles) { $this->titles = $titles; }

    public function getPageTitles(): array
    {
        return $this->titles;
    }
}
