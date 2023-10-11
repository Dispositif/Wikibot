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
    final public const FAKE_USER_AGENT = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36";
    protected const API_GET_IP = 'https://api64.ipify.org';

    protected const DEFAULT_MAX_REDIRECTS = 5;
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
            'timeout' => $options['timeout'] ?? 20,
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

    public function getIp(): ?string
    {
        $response = $this->client->get(self::API_GET_IP, [
            'timeout' => 10,
            'headers' => [
                'User-Agent' => self::FAKE_USER_AGENT,
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
