<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Application\InfrastructurePorts\HttpClientInterface;
use App\Domain\ExternLink\LinkVerdict;
use App\Domain\ExternLink\Validators\ArchiveNoContentValidator;
use App\Domain\ExternLink\WikiwixContentResolver;
use App\Domain\InfrastructurePorts\DeadlinkArchiverInterface;
use App\Domain\Models\WebarchiveDTO;
use App\Infrastructure\Monitor\NullLogger;
use DateTimeInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Ask Wikiwix whether it holds a snapshot of an URL.
 *
 * Wikiwix used to answer a JSON summary on "index2.php?apiresponse=1&url=" (status,
 * timestamp, longformurl). Since its 2026 migration to a React SPA that endpoint returns
 * the viewer's HTML shell whatever the request, and the longform "/cache/<timestamp>/<url>"
 * URLs it advertised 404 — so this adapter silently found nothing at all, and no dead link
 * was ever archived through Wikiwix any more.
 *
 * The SPA bundle only calls two endpoints (token.php, page.php — grepped, there is no
 * other), neither of which describes a snapshot : there is no machine-readable listing
 * left. So "does a snapshot exist ?" is answered the only way that remains — run the
 * viewer's own handshake (WikiwixContentResolver) and look at what comes back : the
 * archived page itself, or Wikiwix's "Unknown page" soft 404 (HTTP 200 over an <h1>404</h1>).
 *
 * Consequence : no snapshot DATE is available any more (WebarchiveDTO carries null, which
 * it already allowed and which nothing consumes today), and no snapshot can be picked by
 * date — Wikiwix keeps one version per URL anyway.
 */
class WikiwixAdapter implements DeadlinkArchiverInterface
{
    final public const ARCHIVER_NAME = '[[Wikiwix]]';

    /** the reader-facing viewer page, the one that belongs in |url= */
    private const VIEWER_URL = 'https://archive.wikiwix.com/cache/index2.php?url=';
    private const TIMEOUT = 20;

    private readonly WikiwixContentResolver $contentResolver;

    public function __construct(
        protected readonly HttpClientInterface $externHttpClient,
        protected readonly LoggerInterface     $log = new NullLogger(),
        ?WikiwixContentResolver                $contentResolver = null
    )
    {
        $this->contentResolver = $contentResolver ?? new WikiwixContentResolver($externHttpClient, $log);
    }

    /**
     * @param DateTimeInterface|null $date ignored : Wikiwix exposes no way to pick a
     *     snapshot by date since the SPA migration (see class docblock).
     */
    public function searchWebarchive(string $url, ?DateTimeInterface $date = null): ?WebarchiveDTO
    {
        $viewerUrl = self::VIEWER_URL . rawurlencode($url);

        $contentUrl = $this->contentResolver->resolveContentUrl($viewerUrl);
        if ($contentUrl === null) {
            $this->log->debug('WikiwixAdapter: handshake failed, no archive lookup possible');

            return null;
        }

        $body = $this->fetchBody($contentUrl);
        if ($body === null) {
            return null;
        }

        // same markers as the crawl pipeline uses on a Wikiwix answer, single source of truth
        $noContent = new ArchiveNoContentValidator(['meta' => []], $viewerUrl, $body, $this->log);
        if ($noContent->check() === LinkVerdict::KeepUrlAsIs) {
            $this->log->debug('WikiwixAdapter: no snapshot for ' . $url);

            return null;
        }

        return new WebarchiveDTO(self::ARCHIVER_NAME, $url, $viewerUrl, null);
    }

    /**
     * Wikiwix keeps (at most) one snapshot per URL and has no CDX-style listing, so this
     * just wraps searchWebarchive().
     *
     * @return WebarchiveDTO[]
     */
    public function searchWebarchiveCandidates(string $url, ?DateTimeInterface $date = null, int $limit = 5): array
    {
        $single = $this->searchWebarchive($url, $date);

        return $single instanceof WebarchiveDTO ? [$single] : [];
    }

    private function fetchBody(string $contentUrl): ?string
    {
        try {
            $response = $this->externHttpClient->get($contentUrl, [
                'timeout' => self::TIMEOUT,
                'allow_redirects' => true,
                'http_errors' => false,
                'headers' => ['User-Agent' => getenv('USER_AGENT')],
                'verify' => false,
            ]);
        } catch (Throwable $e) {
            $this->log->debug('WikiwixAdapter: page.php request failed: ' . $e->getMessage());

            return null;
        }

        if ($response->getStatusCode() !== 200) {
            $this->log->debug('WikiwixAdapter: page.php HTTP ' . $response->getStatusCode());

            return null;
        }

        return $response->getBody()->getContents();
    }
}
