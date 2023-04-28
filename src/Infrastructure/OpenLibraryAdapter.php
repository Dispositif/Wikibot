<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\InfrastructurePorts\BookApiInterface;
use App\Domain\Publisher\OpenLibraryMapper;
use Exception;
use GuzzleHttp\Client;
use Normalizer;

/**
 * Doc : https://openlibrary.org/dev/docs/api/books
 * Class OpenLibraryAdapter.
 */
class OpenLibraryAdapter extends AbstractBookApiAdapter implements BookApiInterface
{
    protected $mapper;

    protected $client;

    public function __construct()
    {
        $this->mapper = new OpenLibraryMapper();
        $this->client = new Client(['timeout' => 30, 'headers' => ['User-Agent' => getenv('USER_AGENT')]]);
    }

    /**
     * @param string $isbn
     *
     * @return array|null
     *
     * @throws Exception
     */
    public function getDataByIsbn(string $isbn): ?array
    {
        $isbn = str_replace([' ', '-'], '', $isbn);

        // todo verify http_build_query() enc_type parameter
        $url = 'https://openlibrary.org/api/books?'.http_build_query(
                [
                    'bibkeys' => sprintf('ISBN:%s', urlencode($isbn)),
                    'format' => 'json',
                    'jscmd' => 'details', // 'data' or 'details'
                ]
            );

        $response = $this->client->get($url);

        if (200 !== $response->getStatusCode()) {
            throw new Exception('response error '.$response->getStatusCode());
        }
        $json = $response->getBody()->getContents();

        if (empty($json)) {
            return null;
        }
        $json = Normalizer::normalize($json);
        $allData = json_decode($json, true, 512, JSON_THROW_ON_ERROR) ?? [];
        // Warning : response structure is different with jscmd = data/details
        return $allData[array_key_first($allData)] ?? null;
    }
}
