<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Infrastructure;

use App\Application\InfrastructurePorts\HttpClientInterface;
use App\Application\InfrastructurePorts\PageListForAppInterface;
use App\Domain\Exceptions\ConfigException;
use App\Domain\InfrastructurePorts\PageListInterface;
use Exception;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Throwable;

/**
 * https://fr.wikipedia.org/w/api.php?action=help&modules=query%2Bsearch
 * https://www.mediawiki.org/wiki/Help:CirrusSearch#Insource
 * raw https://fr.wikipedia.org/w/api.php?action=query&list=search&srsearch=%22https://books.google%22%20insource:/\%3Cref\%3Ehttps\:\/\/books\.google/&formatversion=2&format=json
 * Dirty.
 * Class CirrusSearch
 *
 * @package App\Infrastructure
 */
class CirrusSearch implements PageListInterface, PageListForAppInterface
{
    final public const BASE_URL = 'https://fr.wikipedia.org/w/api.php';
    public const NAMESPACE_MAIN = 0;
    private const SEARCH_CONTINUE_FILENAME = __DIR__ . '/../../resources/cirrusSearch-HASH.txt'; // move to config
    /**
     * @var array|string[]
     */
    protected array $requestParams = [];

    private array $defaultParams
        = [
            'action' => 'query',
            'list' => 'search',
            'formatversion' => '2',
            'format' => 'json',
            'srnamespace' => '0',
            'srlimit' => '100',
        ];
    private readonly HttpClientInterface $client;

    /**
     * $options : "continue" => true for continue search
     */
    public function __construct(private readonly array $params, private ?array $options = [])
    {
        $this->client = ServiceFactory::getHttpClient();
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

        if (($this->options['continue'] ?? false) &&  (!empty($arrayResp['continue']['sroffset']))) {
            $continueOffset = (int) $arrayResp['continue']['sroffset'];
            $this->saveOffsetInFile($continueOffset);
        }
        if (!isset($arrayResp['query']) || empty($arrayResp['query']['search'])) {
            return [];
        }
        $results = $arrayResp['query']['search'];

        $titles = [];
        foreach ($results as $res) {
            if (!empty($res['title'])) {
                $titles[] = trim((string) $res['title']); // trim utile ?
            }
        }

        if (isset($this->options['reverse']) && $this->options['reverse'] === true) {
            krsort($titles);
        }

        return $titles;
    }

    public function setOptions(?array $options): void
    {
        $this->options = $options;
    }

    private function getURL(): string
    {
        if (empty($this->params['srsearch'])) {
            throw new InvalidArgumentException('No "srsearch" argument in params.');
        }

        $this->requestParams = array_merge($this->defaultParams, $this->params);
        if ($this->options['continue'] ?? false) {
            $this->requestParams['sroffset'] = $this->getOffsetFromFile($this->requestParams);
            echo sprintf("Extract offset %s from file \n", $this->requestParams['sroffset']);
        }
        // RFC3986 : space => %20
        $query = http_build_query($this->requestParams, 'bla', '&', PHP_QUERY_RFC3986);

        return self::BASE_URL.'?'.$query;
    }

    /**
     * todo Wiki API ?
     *
     * @throws ConfigException
     * @throws Exception
     */
    private function httpRequest(): array
    {
        $e = null;
        $url = $this->getURL();
        if ($url === '' || $url === '0') {
            throw new ConfigException('CirrusSearch null URL');
        }

        // improve with curl options ?
        $response = $this->client->get($url);
        /**
         * @var $response Response
         */
        if ($response->getStatusCode() !== 200) {
            throw new Exception(
                'CirrusSearch error : '.$response->getStatusCode().' '.$response->getReasonPhrase()
            );
        }
        $json = $response->getBody()->getContents();
        if (empty($json)) {
            return [];
        }
        try {
            $array = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }

        return $array;
    }

    private function getOffsetFromFile(array $allParams): int
    {
        $hash = $this->hashSearchParams($allParams);
        $file = str_replace('HASH', $hash, self::SEARCH_CONTINUE_FILENAME);
        if (!file_exists($file)) {
            return 0;
        }

        return (int) trim(file_get_contents($file));
    }

    private function saveOffsetInFile(int $continueOffset = 0): void
    {
        $hash = $this->hashSearchParams($this->requestParams);
        $file = str_replace('HASH', $hash, self::SEARCH_CONTINUE_FILENAME);

        file_put_contents($file, $continueOffset);
    }

    private function hashSearchParams(array $params): string
    {
        if (empty($params)) {
            throw new InvalidArgumentException('No search argument in params.');
        }
        if (isset($params['sroffset'])) {
            unset($params['sroffset']);
        }

        return md5(implode('', $params));
    }
}
