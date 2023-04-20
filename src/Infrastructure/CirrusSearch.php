<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Infrastructure;

use App\Domain\Exceptions\ConfigException;
use App\Domain\InfrastructurePorts\PageListInterface;
use GuzzleHttp\Psr7\Response;
use HttpException;

/**
 * https://fr.wikipedia.org/w/api.php?action=help&modules=query%2Bsearch
 * https://www.mediawiki.org/wiki/Help:CirrusSearch#Insource
 * raw https://fr.wikipedia.org/w/api.php?action=query&list=search&srsearch=%22https://books.google%22%20insource:/\%3Cref\%3Ehttps\:\/\/books\.google/&formatversion=2&format=json
 * Dirty.
 * Class CirrusSearch
 *
 * @package App\Infrastructure
 */
class CirrusSearch implements PageListInterface
{
    public const BASE_URL = 'https://fr.wikipedia.org/w/api.php';

    private $params;
    private $options;
    private $defaultParams
        = [
            'action' => 'query',
            'list' => 'search',
            'formatversion' => '2',
            'format' => 'json',
            'srnamespace' => '0',
            'srlimit' => '100',
        ];
    private $client;

    public function __construct(array $params, ?array $options = [])
    {
        $this->params = $params;
        $this->options = $options;
        $this->client = ServiceFactory::httpClient(['timeout' => 300]);
    }

    /**
     * todo move to ApiSearch
     *
     * @return array
     * @throws ConfigException
     */
    public function getPageTitles(): array
    {
        $arrayResp = $this->httpRequest();

        if (!isset($arrayResp['query']) || empty($arrayResp['query']['search'])) {
            return [];
        }
        $results = $arrayResp['query']['search'];

        $titles = [];
        foreach ($results as $res) {
            if (!empty($res['title'])) {
                $titles[] = trim($res['title']); // trim utile ?
            }
        }

        if (isset($this->options['reverse']) && $this->options['reverse'] === true) {
            krsort($titles);
        }

        return $titles;
    }

    /**
     * @param array|null $options
     */
    public function setOptions(?array $options): void
    {
        $this->options = $options;
    }

    private function getURL(): string
    {
        if (empty($this->params['srsearch'])) {
            throw new \InvalidArgumentException('No "srsearch" argument in params.');
        }

        $allParams = array_merge($this->defaultParams, $this->params);
        // RFC3986 : space => %20
        $query = http_build_query($allParams, 'bla', '&', PHP_QUERY_RFC3986);

        return self::BASE_URL.'?'.$query;
    }

    /**
     * todo Wiki API ?
     *
     * @return array
     * @throws ConfigException
     * @throws HttpException
     */
    private function httpRequest(): array
    {
        $e = null;
        if ($this->getURL() === '' || $this->getURL() === '0') {
            throw new ConfigException('CirrusSearch null URL');
        }

        $response = $this->client->get($this->getURL());
        /**
         * @var $response Response
         */
        if ($response->getStatusCode() !== 200) {
            throw new HttpException(
                'CirrusSearch error : '.$response->getStatusCode().' '.$response->getReasonPhrase()
            );
        }
        $json = $response->getBody()->getContents();
        if (empty($json)) {
            return [];
        }
        try {
            $array = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            throw new \Exception($e->getMessage(), $e->getCode(), $e);
        }

        return $array;
    }
}
