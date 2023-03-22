<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain;

use App\Application\Http\ExternHttpClient;
use DomainException;
use Exception;
use Psr\Log\LoggerInterface as Log;

class ExternPageFactory
{
    private function __construct() { }

    /**
     * @param          $url
     * @param Log|null $log
     *
     * @return ExternPage
     * @throws Exception
     */
    public static function fromURL($url, ?Log $log = null): ExternPage
    {
        if (!ExternHttpClient::isHttpURL($url)) {
            throw new Exception('string is not an URL '.$url);
        }
        $adapter = new ExternHttpClient($log);
        $html = $adapter->getHTML($url, true);
        if (empty($html)) {
            throw new DomainException('No HTML from requested URL '.$url);
        }

        return new ExternPage($url, $html, $log);
    }

}
