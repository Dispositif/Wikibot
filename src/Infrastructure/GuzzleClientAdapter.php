<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Application\InfrastructurePorts\HttpClientInterface;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

class GuzzleClientAdapter implements HttpClientInterface
{
    protected Client $client;

    public function __construct(array $options = [])
    {
        // https://docs.guzzlephp.org/en/6.5/request-options.html
        $this->client = new Client(
            [
                'timeout' => 20,
                'allow_redirects' => true,
//                or replace "true" with: [
//                    'max'             => 5,
//                    'strict'          => false,
//                    'referer'         => false,
//                    'protocols'       => ['http', 'https'],
//                    'track_redirects' => false
//                ]
                'headers' => ['User-Agent' => getenv('USER_AGENT')],
                'verify' => false, // CURLOPT_SSL_VERIFYHOST
//                    'http_errors' => false, // no Exception on 4xx 5xx
                //                'proxy'           => '192.192.192.192:10',
            ]
        );
    }

    public function get(string|UriInterface $uri, array $options = []): ResponseInterface
    {
        return $this->client->get($uri, $options);
    }

    public function request($method, $uri, array $options = []): ResponseInterface
    {
        return $this->client->request($method, $uri, $options);
    }
}