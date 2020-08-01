<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);


namespace App\Application\Http;


interface HttpClientInterface
{
    public function getHTML(string $url, ?bool $normalized=false): ?string;
}
