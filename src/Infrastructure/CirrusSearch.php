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
use Mediawiki\Api\SimpleRequest;
use Mediawiki\Api\UsageException;
use Throwable;

/**
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
    /**
     * Route the search through the logged-in MediaWiki API session instead of an
     * anonymous HTTP GET. Only point : a bot account has "apihighlimits", so
     * srlimit=SRLIMIT_MAX yields 5000 titles per request instead of 500.
     */
    public const OPTION_APILOGIN = 'apilogin';
    public const SRSORT_NONE = 'none';
    public const SRSORT_RANDOM = 'random';
    public const SRSORT_LAST_EDIT_DESC = 'last_edit_desc';
    public const SRQIPROFILE_POPULAR_INCLINKS_PV = 'popular_inclinks_pv'; // nombre de vues de la page :)
    public const SRQIPROFILE_DEFAULT = 'engine_autoselect';
    /**
     * Self-adjusting limit : resolves server-side to whatever the caller is allowed
     * (500 anonymous, 5000 with apihighlimits, i.e. OPTION_APILOGIN on a bot account).
     * Prefer it over a hardcoded '5000', which is rejected with a warning and silently
     * clamped to 500 when the right is missing.
     */
    public const SRLIMIT_MAX = 'max';

    protected const BASE_URL = 'https://fr.wikipedia.org/w/api.php'; // todo move config
    protected const CONTINUE_OFFSET_FILENAME = __DIR__ . '/../../resources/cirrusSearch-{HASH}.txt'; // todo move config

    /** Saturated shared pool on the Wikimedia side : worth waiting for, unlike a bad query. */
    protected const TRANSIENT_ERROR_CODES = ['cirrussearch-regex-too-busy-error', 'cirrussearch-too-busy-error'];
    protected const MAX_RETRY = 3;
    protected const RETRY_SLEEP_SECONDS = 30;

    protected array $requestParams = [];
    protected array $defaultParams
        = [
            'action' => 'query',
            'list' => 'search',
            'formatversion' => '2',
            'format' => 'json',
            'srnamespace' => 0,
            'srlimit' => '500', // max 500 péon, 5000 bot/admin (see SRLIMIT_MAX)
            'srprop' => 'size|wordcount|timestamp', // default 'size|wordcount|timestamp|snippet'
        ];
    protected readonly HttpClientInterface $client;

    /**
     * $options : "continue" => true for continue search
     */
    // Not readonly : stream() mutates params['sroffset'] between pages to paginate.
    public function __construct(protected array $params, protected ?array $options = [])
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

        return $this->extractTitles($arrayResp);
    }

    /**
     * Same results as getPageTitles(), but loops internally across up to $maxPages result
     * pages (500 titles each) and yields as it goes, instead of the caller instantiating
     * several CirrusSearch with manual "sroffset" and array_merge()-ing the results
     * (see git history of lastExternRefProcess.php).
     *
     * Independent of the file-based OPTION_CONTINUE mechanism (that one persists an
     * offset across separate process runs) — don't combine the two.
     *
     * Two server-side ceilings apply : sroffset is capped at 10000
     * ("cirrussearch-offset-too-large" beyond that), and srsort=random reshuffles on
     * every request, so paginating it yields duplicates and gaps rather than more
     * coverage — with random, ask for one big page instead.
     *
     * @return iterable<string>
     * @throws ConfigException
     */
    public function stream(int $maxPages = 10, int $sleepBetweenPages = 0): iterable
    {
        if ($maxPages > 1 && ($this->params['srsort'] ?? null) === self::SRSORT_RANDOM) {
            echo "CirrusSearch: paginating srsort=random is meaningless (order reshuffles per request).\n";
        }

        $pages = 0;
        while (true) {
            $arrayResp = $this->httpRequest();
            // "yield from" per page (each restarting at key 0) would let iterator_to_array()
            // silently collide/overwrite across pages ; yielding one by one instead gives
            // the generator its own auto-incrementing keys across the whole stream.
            foreach ($this->extractTitles($arrayResp) as $title) {
                yield $title;
            }

            $pages++;
            $nextOffset = $arrayResp['continue']['sroffset'] ?? null;
            if ($nextOffset === null || $pages >= $maxPages) {
                return;
            }
            $this->params['sroffset'] = (int)$nextOffset;

            if ($sleepBetweenPages > 0) {
                sleep($sleepBetweenPages);
            }
        }
    }

    private function extractTitles(array $arrayResp): array
    {
        if (!isset($arrayResp['query']) || empty($arrayResp['query']['search'])) {
            return [];
        }

        $titles = [];
        foreach ($arrayResp['query']['search'] as $res) {
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
     * insource:/regex/ searches go through a shared pool on the Wikimedia side, which
     * hands back "cirrussearch-regex-too-busy-error" when it is saturated — a transient
     * condition, not a bad query, and one that two bot crons firing at the same minute
     * hit easily. Retried with a plain backoff rather than propagated, since the caller
     * has no worklist at all otherwise.
     *
     * @throws ConfigException
     * @throws Exception
     */
    protected function httpRequest(): array
    {
        for ($attempt = 1; ; $attempt++) {
            $response = ($this->options[self::OPTION_APILOGIN] ?? false)
                ? $this->apiLoggedRequest()
                : $this->anonymousRequest();

            $errorCode = (string)($response['error']['code'] ?? '');
            if ($errorCode === '') {
                return $response;
            }
            // static:: and not self:: : subclasses (tests, other wikis) must be able to
            // retune the backoff, and self:: would freeze it to this class at compile time
            if (!in_array($errorCode, static::TRANSIENT_ERROR_CODES, true) || $attempt >= static::MAX_RETRY) {
                throw new Exception(
                    sprintf('CirrusSearch API error: %s %s', $errorCode, $response['error']['info'] ?? '')
                );
            }

            $sleep = $attempt * static::RETRY_SLEEP_SECONDS;
            echo sprintf("CirrusSearch busy (%s), retry %d in %ds\n", $errorCode, $attempt, $sleep);
            sleep($sleep);
        }
    }

    /**
     * Same query, sent through the authenticated MediaWiki API session, so a bot
     * account's apihighlimits applies (srlimit=max => 5000 instead of 500).
     *
     * @throws ConfigException
     * @throws Exception
     */
    protected function apiLoggedRequest(): array
    {
        $params = $this->buildRequestParams();
        // action is a SimpleRequest argument, and the library forces format=json itself
        unset($params['action'], $params['format']);

        try {
            $response = ServiceFactory::getMediawikiApi()->getRequest(new SimpleRequest('query', $params));
        } catch (UsageException $e) {
            // normalized to the anonymous path's shape, so httpRequest() arbitrates retries once
            return ['error' => ['code' => $e->getApiCode(), 'info' => $e->getRawMessage()]];
        }
        if (!is_array($response)) {
            throw new Exception('CirrusSearch: unexpected API response type');
        }
        $this->echoApiWarnings($response);

        return $response;
    }

    /**
     * API warnings are the only place where a silently degraded search shows up :
     * an srlimit clamped back to 500 (missing apihighlimits), or the killer one,
     * "The regex search timed out, so only partial results are available".
     */
    protected function echoApiWarnings(array $response): void
    {
        foreach ($response['warnings'] ?? [] as $module => $warning) {
            $text = is_array($warning) ? implode(' ', $warning) : (string)$warning;
            echo sprintf("CirrusSearch API warning [%s]: %s\n", $module, trim($text));
        }
    }

    /**
     * @throws ConfigException
     * @throws Exception
     */
    protected function anonymousRequest(): array
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
        $this->echoApiWarnings($array);

        return $array;
    }

    /**
     * Merges defaults + caller params + the persisted continue offset, and memorizes
     * the result in $requestParams (which saveOffsetInFile() hashes afterwards).
     */
    protected function buildRequestParams(): array
    {
        if (empty($this->params['srsearch'])) {
            throw new InvalidArgumentException('No "srsearch" argument in params.');
        }

        $this->requestParams = array_merge($this->defaultParams, $this->params);
        if ($this->options[self::OPTION_CONTINUE] ?? false) {
            $this->requestParams['sroffset'] = $this->getOffsetFromFile($this->requestParams);
            echo sprintf(
                "Extract offset %s from file %s \n",
                $this->requestParams['sroffset'], $this->hashSearchParams($this->requestParams)
            );
        }

        return $this->requestParams;
    }

    protected function getURL(): string
    {
        // RFC3986 : space => %20
        $query = http_build_query($this->buildRequestParams(), 'bla', '&', PHP_QUERY_RFC3986);

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
