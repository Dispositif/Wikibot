<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink;

use App\Application\InfrastructurePorts\HttpClientInterface;
use App\Infrastructure\Monitor\NullLogger;
use JsonException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Resolve a Wikiwix cache URL to the URL actually serving the archived HTML.
 *
 * Since Wikiwix's 2026 migration to a React SPA, /cache/ URLs only return a JS shell
 * (see ArchiveNoContentValidator) : the snapshot is loaded client-side through a two-step
 * handshake, reproduced here as the SPA's own bundle does it —
 *   1. GET /cache/token.php?url=<original> => JSON [l, c, d]
 *   2. wait, then GET /cache/page.php?a=<a>&b=<b>&url=<original> => the archived HTML,
 *      with a and b derived from the triple by the formula below.
 * Tokens are single-use (a replay answers 403) and rejected when the request comes too
 * early (checked 2026-08-19 : immediate => 403, 2 s and 13 s => 200), hence the wait.
 *
 * Crawl permission for wikiwix.com confirmed by the bot operator (2026-08-19), see
 * RobotsTxtChecker::AGREED_CRAWL_DOMAINS.
 *
 * Undocumented protocol read off a minified bundle : expect it to break the day Wikiwix
 * recompiles. Failure is safe and silent — resolveContentUrl() returns null, the crawl
 * falls back to the plain cache URL, and ArchiveNoContentValidator then leaves the link
 * untouched rather than inventing metadata.
 */
class WikiwixContentResolver
{
    private const TOKEN_ENDPOINT = 'https://archive.wikiwix.com/cache/token.php';
    private const PAGE_ENDPOINT = 'https://archive.wikiwix.com/cache/page.php';
    private const TIMEOUT = 20;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface     $log = new NullLogger(),
        /** the SPA waits 2 s between the two calls; a margin costs nothing here (0 in tests) */
        private readonly int                 $handshakeDelay = 3
    )
    {
    }

    public function resolveContentUrl(string $wikiwixUrl): ?string
    {
        $original = $this->originalUrl($wikiwixUrl);
        if ($original === null) {
            return null;
        }

        $token = $this->requestToken($original);
        if ($token === null) {
            return null;
        }

        [$l, $c, $d] = $token;
        $a = ($l + $d / 2) / 2;
        $b = $c - $a - (($d / 2) - $a) / 3 * 2;

        if ($this->handshakeDelay > 0) {
            sleep($this->handshakeDelay);
        }

        $contentUrl = self::PAGE_ENDPOINT . '?' . http_build_query(
            ['a' => $this->jsNumber($a), 'b' => $this->jsNumber($b), 'url' => $original]
        );
        $this->log->debug('Wikiwix content URL resolved : ' . $contentUrl, ['stats' => 'externref.wikiwix.contentResolved']);

        return $contentUrl;
    }

    /**
     * The archived URL as the SPA reads it, i.e. URLSearchParams-decoded : "%26" inside
     * the cache URL's own "url=" param is a query separator of the ORIGINAL URL, not a
     * literal percent sequence.
     */
    private function originalUrl(string $wikiwixUrl): ?string
    {
        $original = WikiwixUrl::extractOriginalUrl($wikiwixUrl);

        return ($original === null) ? null : urldecode($original);
    }

    /**
     * @return array{0: float, 1: float, 2: float}|null
     */
    private function requestToken(string $originalUrl): ?array
    {
        $options = ['timeout' => self::TIMEOUT, 'http_errors' => false];
        $userAgent = getenv('USER_AGENT');
        if (is_string($userAgent) && $userAgent !== '') {
            $options['headers'] = ['User-Agent' => $userAgent];
        }

        try {
            $response = $this->httpClient->get(
                self::TOKEN_ENDPOINT . '?' . http_build_query(['url' => $originalUrl]),
                $options
            );
            if ($response->getStatusCode() !== 200) {
                $this->log->debug('Wikiwix token.php HTTP ' . $response->getStatusCode());

                return null;
            }
            $data = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->log->notice('Wikiwix token.php did not answer JSON — handshake protocol changed ?');

            return null;
        } catch (Throwable $e) {
            $this->log->debug('Wikiwix token.php failed : ' . $e->getMessage());

            return null;
        }

        if (!is_array($data) || count($data) < 3) {
            $this->log->notice('Wikiwix token.php unexpected payload — handshake protocol changed ?');

            return null;
        }

        return [(float)$data[0], (float)$data[1], (float)$data[2]];
    }

    /**
     * Serialize like JavaScript does in the bundle (URLSearchParams stringifies Numbers) :
     * no thousands separator, no exponent, no trailing ".0". In practice Wikiwix builds
     * its triples so that both results are whole numbers.
     */
    private function jsNumber(float $number): string
    {
        if ($number === floor($number) && abs($number) < 1e15) {
            return number_format($number, 0, '.', '');
        }

        return rtrim(rtrim(number_format($number, 10, '.', ''), '0'), '.');
    }
}
