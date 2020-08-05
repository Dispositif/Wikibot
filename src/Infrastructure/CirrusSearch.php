<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);


namespace App\Infrastructure;

use App\Domain\Exceptions\ConfigException;

/**
 * Dirty.
 * Class CirrusSearch
 *
 * @package App\Infrastructure
 */
class CirrusSearch implements PageListInterface
{
    const BASE_URL = 'https://fr.wikipedia.org/w/api.php';

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

    public function __construct(array $params, ?array $options = [])
    {
        $this->params = $params;
        $this->options = $options;
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
     * todo Guzzle or Wiki API
     *
     * @return array
     * @throws ConfigException
     */
    private function httpRequest(): array
    {
        if (!$this->getURL()) {
            throw new ConfigException('CirrusSearch null URL');
        }

        $json = file_get_contents($this->getURL());
        if (false === $json) {
            return [];
        }

        return json_decode($json, true);
    }
}
