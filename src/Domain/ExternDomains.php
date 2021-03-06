<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe/Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain;

use App\Application\Http\ExternHttpClient;

class ExternDomains
{

    /*
     * "http://www.bla.com/fubar" => "bla.com"
     */
    public static function extractSubDomain(string $url): string
    {
        if (!ExternHttpClient::isWebURL($url)) {
            throw new \Exception('string is not an URL '.$url);
        }
        $parseURL = parse_url($url);

        return str_replace('www.', '', $parseURL['host']);
    }

}
