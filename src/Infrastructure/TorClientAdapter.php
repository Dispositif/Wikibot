<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Application\InfrastructurePorts\HttpClientInterface;
use App\Domain\Exceptions\ConfigException;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleTor\Middleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * TODO : param 'http_errors' for exception or not behind Tor :)
 * TODO : options for user-agent
 * Lib megahertz/guzzle-tor : https://github.com/megahertz/guzzle-tor/tree/master
 */
class TorClientAdapter extends GuzzleClientAdapter implements HttpClientInterface
{
    protected const API_GET_IP = 'https://api64.ipify.org';

    // Fallback only, used if FAKE_USER_AGENT is unset (e.g. some test envs) : normally
    // getenv('FAKE_USER_AGENT') wins, see getIp(). Can't be a class const : constant
    // expressions can't call getenv(), so this is deliberately just a last resort, not
    // the source of truth — keeping it in sync with .env is not required.
    private const FALLBACK_USER_AGENT = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.7922.76 Safari/537.36";

    // Lowered from 5: each hop is a fresh getRecursive() call at the full per-request
    // timeout (see fetch() below), so a slow redirect chain multiplies wall-clock time
    // against the request timeout — 3 hops is enough for real-world redirect chains
    // without letting one stuck URL stall a whole batch run.
    protected const DEFAULT_MAX_REDIRECTS = 3;
    public const DEFAULT_TIMEOUT = 35;
    protected int $maxRedirects = 0;

    public function __construct(array $options = [])
    {
        $proxy = getenv('TOR_PROXY');
        $torControl = getenv('TOR_CONTROL');
        if (!$proxy || !$torControl) {
            throw new ConfigException('TOR proxy or control not defined in .env');
        }

        $stack = new HandlerStack();
        $stack->setHandler(new CurlHandler());
        $stack->push(Middleware::tor($proxy, $torControl));

        $this->client = new Client([
            'handler' => $stack,
            // Same reasoning as ExternPageFactory's per-request override (which normally
            // wins anyway): cold Tor circuit-building alone can take >10s (see GET_IP_TIMEOUT).
            'timeout' => $options['timeout'] ?? self::DEFAULT_TIMEOUT,
            'allow_redirects' => $options['allow_redirects'] ?? true,
            'headers' => $options['headers'] ?? ['User-Agent' => getenv('USER_AGENT')],
            'verify' => false,
            // 'http_errors' => false, // no Exception on 4xx 5xx
        ]);

        $this->validateIpOrException();
    }

    /**
     * @throws Exception
     */
    protected function validateIpOrException(): void
    {
        $torIp = $this->getIp();
        if (!$torIp) {
            throw new Exception('TOR IP not found');
        }
        echo "TOR IP : $torIp \n";
    }

    /** Generous : this is the very first request through a cold Tor instance —
     * circuit-building (3 hops) with nothing cached yet routinely takes >10s,
     * confirmed empirically (~12s) after ruling out a bootstrap-readiness issue. */
    private const GET_IP_TIMEOUT = 30;

    public function getIp(): ?string
    {
        $response = $this->client->get(self::API_GET_IP, [
            'timeout' => self::GET_IP_TIMEOUT,
            'headers' => [
                'User-Agent' => getenv('FAKE_USER_AGENT') ?: self::FALLBACK_USER_AGENT,
            ],
            'verify' => false, // CURLOPT_SSL_VERIFYHOST
            'http_errors' => false, // no Exception on 4xx 5xx
        ]);

        if ($response->getStatusCode() === 200) {
            return $response->getBody()->getContents();
        }

        return null;
    }

    public function get(string|UriInterface $uri, array $options = []): ResponseInterface
    {
        if (isset($options['allow_redirects']) && $options['allow_redirects'] !== false) {
            $this->maxRedirects = self::DEFAULT_MAX_REDIRECTS;
        }

        return $this->getRecursive($uri, $options);
    }

    /**
     * todo : add redirect http referer
     */
    private function getRecursive(UriInterface|string $uri, array $options, int $loop = 0): ResponseInterface
    {
        $response = $this->client->get($uri, $options);

        // Redirect 3xx
        if ($response->getStatusCode() >= 300 && $response->getStatusCode() < 400) {
            $redirectUri = $response->getHeader('location')[0] ?? null;
            if ($loop >= $this->maxRedirects || !$redirectUri) {
                throw new Exception('TorClientAdapter::get Error too many redirects ' . $response->getStatusCode());
            }
            $loop++;
            return $this->getRecursive($redirectUri, $options, $loop);
        }

        // Error 4xx 5xx
        if ($response->getStatusCode() >= 400) {
            throw new Exception($response->getStatusCode() . ' ' . $response->getReasonPhrase());
        }

        return $response;
    }

    /**
     * @deprecated use get() or implement. Trying to HEAD or POST with Tor ?!
     */
    public function request($method, $uri, array $options = []): ResponseInterface
    {
        throw new Exception('NOT YET IMPLEMENTED z944');
    }
}
