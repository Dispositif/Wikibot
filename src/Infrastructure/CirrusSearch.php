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
 * TODO injecter API session sinon limité à 500 results !
 * https://www.mediawiki.org/wiki/Help:CirrusSearch
 * https://fr.wikipedia.org/w/api.php?action=help&modules=query%2Bsearch
 * https://www.mediawiki.org/wiki/Help:CirrusSearch#Insource
 * raw https://fr.wikipedia.org/w/api.php?action=query&list=search&srsearch=%22https://books.google%22%20insource:/\%3Cref\%3Ehttps\:\/\/books\.google/&formatversion=2&format=json
 * Dirty.
 * Class CirrusSearch
 */
class CirrusSearch implements PageListInterface, PageListForAppInterface
{
    public const OPTION_CONTINUE = 'continue';
    public const OPTION_REVERSE = 'reverse';
    public const OPTION_APILOGIN = 'apilogin';
    public const SRSORT_NONE = 'none';
    public const SRSORT_RANDOM = 'random';
    public const SRSORT_LAST_EDIT_DESC = 'last_edit_desc';
    public const SRQIPROFILE_POPULAR_INCLINKS_PV = 'popular_inclinks_pv'; // nombre de vues de la page :)
    public const SRQIPROFILE_DEFAULT = 'engine_autoselect';

    protected const BASE_URL = 'https://fr.wikipedia.org/w/api.php'; // todo move config
    protected const CONTINUE_OFFSET_FILENAME = __DIR__ . '/../../resources/cirrusSearch-{HASH}.txt'; // todo move config

    protected array $requestParams = [];
    protected array $defaultParams
        = [
            'action' => 'query',
            'list' => 'search',
            'formatversion' => '2',
            'format' => 'json',
            'srnamespace' => 0,
            'srlimit' => '500', // max 500 péon, 5000 bot/admin
            'srprop' => 'size|wordcount|timestamp', // default 'size|wordcount|timestamp|snippet'
        ];
    protected readonly HttpClientInterface $client;

    /**
     * $options : "continue" => true for continue search
     */
    public function __construct(protected readonly array $params, protected ?array $options = [])
    {
        $this->client = ServiceFactory::getHttpClient();
    }

    /**
     * @return array
     * @throws ConfigException
     */
    public function getPageTitles(): array
    {
        $arrayResp = $this->httpRequest();

        if ($this->options[self::OPTION_CONTINUE] ?? false) {
            $continueOffset = 0;
            if (!empty($arrayResp['continue']['sroffset'])) {
                $continueOffset = (int)$arrayResp['continue']['sroffset'];
            }
            $this->saveOffsetInFile($continueOffset);
        }

        if (!isset($arrayResp['query']) || empty($arrayResp['query']['search'])) {
            return [];
        }
        $results = $arrayResp['query']['search'];

        $titles = [];
        foreach ($results as $res) {
            if (!empty($res['title'])) {
                $titles[] = trim((string)$res['title']); // trim utile ?
            }
        }

        if (isset($this->options[self::OPTION_REVERSE]) && $this->options[self::OPTION_REVERSE] === true) {
            krsort($titles);
        }

        return $titles;
    }

    /**
     * todo Wiki API ?
     * @throws ConfigException
     * @throws Exception
     */
    protected function httpRequest(): array
    {
        $url = $this->getURL();
        if ($url === '' || $url === '0') {
            throw new ConfigException('CirrusSearch null URL');
        }

        // improve with curl options ?
        $response = $this->client->get($url); // TODO refac with wiki API login
        /**
         * @var $response Response
         */
        if ($response->getStatusCode() !== 200) {
            throw new Exception(
                'CirrusSearch error : ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase()
            );
        }
        $json = $response->getBody()->getContents();
        if (empty($json)) {
            return [];
        }
        try {
            $array = json_decode((string)$json, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }

        return $array;
    }

    protected function getURL(): string
    {
        if (empty($this->params['srsearch'])) {
            throw new InvalidArgumentException('No "srsearch" argument in params.');
        }

        $this->requestParams = array_merge($this->defaultParams, $this->params);
        if ($this->options[self::OPTION_CONTINUE] ?? false) {
            $this->requestParams['sroffset'] = $this->getOffsetFromFile($this->requestParams);
            echo sprintf("Extract offset %s from file \n", $this->requestParams['sroffset']);
        }
        // RFC3986 : space => %20
        $query = http_build_query($this->requestParams, 'bla', '&', PHP_QUERY_RFC3986);

        return self::BASE_URL . '?' . $query;
    }

    protected function getOffsetFromFile(array $allParams): int
    {
        $hash = $this->hashSearchParams($allParams);
        $file = str_replace('{HASH}', $hash, self::CONTINUE_OFFSET_FILENAME);
        if (!file_exists($file)) {
            return 0;
        }

        return (int)trim(file_get_contents($file));
    }

    protected function hashSearchParams(array $params): string
    {
        if (empty($params)) {
            throw new InvalidArgumentException('No search argument in params.');
        }
        if (isset($params['sroffset'])) {
            unset($params['sroffset']);
        }

        return md5(implode('', $params));
    }

    protected function saveOffsetInFile(int $continueOffset = 0): void
    {
        $hash = $this->hashSearchParams($this->requestParams);
        $offsetFilename = str_replace('{HASH}', $hash, self::CONTINUE_OFFSET_FILENAME);

        if ($continueOffset === 0 && file_exists($offsetFilename)) {
            @unlink($offsetFilename);
        } else {
            file_put_contents($offsetFilename, $continueOffset);
        }
    }
}
