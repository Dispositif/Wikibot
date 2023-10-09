<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Application\InfrastructurePorts\HttpClientInterface;
use Exception;

class HttpClientFactory
{
    /**
     * @throws Exception si erreur Tor
     */
    public static function create(bool $tor = false): HttpClientInterface
    {
        if ($tor && getenv('TOR_PROXY') && getenv('TOR_CONTROL')) {
            return new TorClientAdapter();
        }

        return new GuzzleClientAdapter();
    }
}
