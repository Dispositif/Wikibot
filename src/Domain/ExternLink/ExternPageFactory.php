<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink;

use App\Application\InfrastructurePorts\HttpClientInterface;
use App\Application\Utils\HttpUtil;
use App\Domain\InfrastructurePorts\InternetDomainParserInterface;
use App\Infrastructure\Monitor\NullLogger;
use App\Infrastructure\TagParser;
use DomainException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\TransferStats;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class ExternPageFactory
{
    public function __construct(protected HttpClientInterface $client, protected LoggerInterface $log = new NullLogger())
    {
    }

    /**
     * Build an ExternPage from a successful fetch. Throws only on a fetch that
     * didn't even succeed as HTTP (caller should check fetch()->isSuccess() first
     * and route unsuccessful results through ExternHttpErrorLogic instead).
     */
    public function fromFetchResult(string $url, FetchResult $fetch, InternetDomainParserInterface $domainParser): ExternPage
    {
        if (!$fetch->isSuccess()) {
            throw new DomainException('fromFetchResult() called on a non-successful FetchResult : ' . $url);
        }

        return new ExternPage($url, (string)$fetch->body, new TagParser(), $domainParser, $this->log);
    }

    /**
     * Fetch a URL and return a typed result — never throws for network/HTTP-level
     * failures (DNS, timeout, 4xx/5xx...), only for a malformed input URL. This
     * replaces the previous throw-and-regex-parse-the-message pattern.
     */
    public function fetch(string $url): FetchResult
    {
        if (!HttpUtil::isHttpURL($url)) {
            throw new DomainException('URL not compatible : ' . $url);
        }

        $stats = null;
        $options = [
            'timeout' => 20,
            'allow_redirects' => true, /* note : marche pas en mode proxy Tor, TorClientAdapter gère lui-même */
            'headers' => ['User-Agent' => getenv('USER_AGENT')],
            'verify' => false,
            'on_stats' => function (TransferStats $s) use (&$stats): void {
                $stats = $s; // fires on success AND failure, gives effective URI + handler error data either way
            },
        ];

        try {
            $response = $this->client->get($url, $options);

            return $this->buildFromResponse($url, $stats, $response);
        } catch (Throwable $e) {
            return $this->buildFromFailure($url, $stats, $e);
        }
    }

    private function buildFromResponse(string $url, ?TransferStats $stats, ResponseInterface $response): FetchResult
    {
        $contentType = $this->extractContentType($response);
        $finalUrl = $this->extractFinalUrl($stats, $url);

        if (in_array('application/pdf', explode(';', $contentType ?? ''), true)) {
            $this->log->debug('Incompatible application/pdf content-type');

            return new FetchResult($url, $finalUrl, $response->getStatusCode(), $contentType, 0, null);
        }

        $rawBody = $response->getBody()->getContents();
        $body = HttpUtil::normalizeHtml($rawBody, $url);

        return new FetchResult(
            $url,
            $finalUrl,
            $response->getStatusCode(),
            $contentType,
            strlen($rawBody),
            $body
        );
    }

    private function buildFromFailure(string $url, ?TransferStats $stats, Throwable $e): FetchResult
    {
        $finalUrl = $this->extractFinalUrl($stats, $url);

        // TorClientAdapter throws a plain Exception with the status *after* the
        // transfer already completed (no default http_errors middleware behind Tor) :
        // on_stats still captured that raw response, so we can recover its status/body.
        $statsResponse = $stats?->getResponse();
        if ($statsResponse instanceof ResponseInterface) {
            return $this->buildFromResponse($url, $stats, $statsResponse);
        }

        if ($e instanceof RequestException && $e->hasResponse()) {
            return $this->buildFromResponse($url, $stats, $e->getResponse());
        }

        $errorKind = $this->classifyErrorKind($stats, $e);
        $this->log->debug('ExternPageFactory fetch failure: ' . $e->getMessage(), ['url' => $url]);

        return new FetchResult($url, $finalUrl, null, null, 0, null, $errorKind, $e->getMessage());
    }

    private function extractFinalUrl(?TransferStats $stats, string $requestedUrl): ?string
    {
        $effectiveUri = $stats?->getEffectiveUri();
        if ($effectiveUri === null) {
            return null;
        }
        $finalUrl = (string)$effectiveUri;

        return $finalUrl !== $requestedUrl ? $finalUrl : null;
    }

    private function extractContentType(ResponseInterface $response): ?string
    {
        $contentType = $response->getHeader('Content-Type');

        return $contentType[0] ?? null;
    }

    /**
     * Best-effort classification of a transfer failure that never produced an HTTP
     * response at all (DNS, empty reply, proxy/SOCKS5 tunnel failure...). Falls back
     * to matching the exception message, since not every handler/middleware in this
     * codebase (guzzle-tor in particular) exposes structured curl error data.
     */
    private function classifyErrorKind(?TransferStats $stats, Throwable $e): FetchErrorKind
    {
        $handlerErrorData = $stats?->getHandlerErrorData();
        $errno = is_array($handlerErrorData) ? ($handlerErrorData['errno'] ?? null) : null;

        return match (true) {
            $errno === 6 => FetchErrorKind::DnsResolutionFailed,
            $errno === 52 => FetchErrorKind::EmptyReply,
            $errno === 28 => FetchErrorKind::ConnectionTimeout,
            $errno === 97 || $errno === 7 => FetchErrorKind::ProxyFailure,
            default => $this->classifyErrorKindFromMessage($e->getMessage()),
        };
    }

    private function classifyErrorKindFromMessage(string $message): FetchErrorKind
    {
        return match (true) {
            (bool)preg_match('#too many redirects#i', $message) => FetchErrorKind::TooManyRedirects,
            (bool)preg_match("#Could not resolve host#i", $message) => FetchErrorKind::DnsResolutionFailed,
            (bool)preg_match('#Empty reply from server#i', $message) => FetchErrorKind::EmptyReply,
            (bool)preg_match("#SOCKS5#i", $message) => FetchErrorKind::ProxyFailure,
            (bool)preg_match('#SSL certificate|SSL connect error#i', $message) => FetchErrorKind::TlsError,
            (bool)preg_match('#timed out#i', $message) => FetchErrorKind::ConnectionTimeout,
            default => FetchErrorKind::Unknown,
        };
    }
}
