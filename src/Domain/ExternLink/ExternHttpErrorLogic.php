<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink;

use App\Domain\InfrastructurePorts\ExternLinkCheckRepositoryInterface;
use App\Domain\Models\Summary;
use App\Infrastructure\Monitor\NullLogger;
use App\Infrastructure\NullExternLinkCheckRepository;
use Psr\Log\LoggerInterface;

/**
 * Doc : https://developer.mozilla.org/fr/docs/Web/HTTP/Status/503
 */
class ExternHttpErrorLogic
{
    final public const LOG_REQUEST_ERROR = __DIR__ . '/../../Application/resources/external_request_error.log';
    protected const LOOSE = true;

    /** How long a 429/503 must go unconfirmed before ExternLinkCheckRepository offers it up again. */
    public const RECHECK_DELAY = 'P2M';

    /**
     * @var int[] statuses that might just be transient (rate-limit, overload) : not
     * converted to a dead link on a single observation, recorded for a later re-check
     * instead (docs/audit-gestion-erreurs-crawl-2026-08.md §9.5/§9.6).
     */
    private const TRANSIENT_ERROR_STATUSES = [429, 503];

    /**
     * @var int[] statuses converted to a dead link immediately (single observation,
     * no recheck delay) : unlike 429/503, these point at the specific requested
     * resource rather than the server being globally overloaded, and are a reliable
     * enough dead-link signal for today's web to act on right away. 451 (legal
     * takedown) is included here too : it's not transient by nature — the content
     * isn't coming back within RECHECK_DELAY — so it belongs with the immediate
     * group rather than being left untouched.
     */
    private const IMMEDIATE_DEAD_STATUSES = [400, 500, 502, 451];

    /**
     * @var int[] "the page exists, access is refused" — never a dead link, and never a
     * candidate for archive substitution either : swapping a paywalled Le Monde URL for
     * an archive of its teaser would be worse than leaving the reference alone.
     * 403 keeps its own branch below (warning level, plus a pending TODO).
     */
    private const ACCESS_DENIED_STATUSES = [401, 402];

    /**
     * @var FetchErrorKind[] network failures treated as a dead link only because LOOSE is enabled.
     * ProxyFailure (SOCKS5/Tor tunnel) is deliberately excluded : it's a failure of the bot's own
     * network path, not evidence the target site is down (see docs/audit-gestion-erreurs-crawl-2026-08.md §9.5).
     */
    private const LOOSE_ERROR_KINDS_TREATED_AS_DEAD
        = [
            FetchErrorKind::EmptyReply,
            FetchErrorKind::DnsResolutionFailed,
        ];

    public function __construct(
        protected DeadLinkTransformer                       $deadLinkTransformer,
        private readonly LoggerInterface                    $log = new NullLogger(),
        private readonly ExternLinkCheckRepositoryInterface  $linkCheckRepository = new NullExternLinkCheckRepository()
    )
    {
    }

    /**
     * $registrableDomain/$pageTitle/$summary vary per call (one instance is reused
     * across many URLs by ExternRefTransformer), so they're call-time params rather
     * than constructor state. $pageTitle absent (recursive archive-URL check, direct
     * unit-test call...) => nothing gets persisted : a failure without a citing page
     * isn't actionable later. $summary lets DeadLinkTransformer report which archiver
     * it actually used, so ExternRefWorker's edit summary doesn't have to re-derive it
     * by string-matching the result (see docs/audit-gestion-erreurs-crawl-2026-08.md §9.8).
     */
    public function manageByFetchResult(
        FetchResult $fetch,
        ?string     $registrableDomain = null,
        ?string     $pageTitle = null,
        ?Summary    $summary = null
    ): string
    {
        $url = $fetch->requestedUrl;

        if ($fetch->httpStatus === 410) {
            $this->log->notice('410 Gone', ['stats' => 'externHttpErrorLogic.410']);

            return ExternRefTransformer::REPLACE_410 ? $this->deadLinkTransformer->formatFromUrl($url, summary: $summary, httpStatus: $fetch->httpStatus) : $url;
        }
        if ($fetch->httpStatus === 404) {
            $this->log->notice('404 Not Found', ['stats' => 'externHttpErrorLogic.404']);

            return ExternRefTransformer::REPLACE_404 ? $this->deadLinkTransformer->formatFromUrl($url, summary: $summary, httpStatus: $fetch->httpStatus) : $url;
        }
        if (in_array($fetch->httpStatus, self::IMMEDIATE_DEAD_STATUSES, true)) {
            $this->log->notice($fetch->httpStatus . ' : ' . $url, ['stats' => 'externHttpErrorLogic.' . $fetch->httpStatus]);

            return $this->deadLinkTransformer->formatFromUrl($url, summary: $summary, httpStatus: $fetch->httpStatus);
        }
        if ($fetch->httpStatus === 403) {
            $this->log->warning('403 Forbidden : ' . $url, ['stats' => 'externHttpErrorLogic.403']);
            // TODO return blankLienWeb without consulté le=...

            return $url;
        }
        if (in_array($fetch->httpStatus, self::ACCESS_DENIED_STATUSES, true)) {
            $this->log->notice(
                sprintf('%d %s : skip %s', $fetch->httpStatus, $fetch->httpStatus === 402 ? 'Payment Required (paywall)' : 'Unauthorized', $url),
                ['stats' => 'externHttpErrorLogic.' . $fetch->httpStatus]
            );

            return $url;
        }

        // A 3xx reaching this point means the redirect chain could NOT be resolved : either
        // the response carried no Location header at all (what cagematch.net and
        // areditions.com actually serve — a 307 with nothing to follow, almost certainly an
        // anti-bot response to a non-browser client), or it ran past
        // TorClientAdapter::DEFAULT_MAX_REDIRECTS hops. Never a dead link : the resource is
        // most likely alive behind a redirect this bot cannot follow. Recorded like 429/503
        // so ExternLinkCheckRepository can offer it up again later rather than concluding now.
        if ($fetch->httpStatus !== null && $fetch->httpStatus >= 300 && $fetch->httpStatus < 400) {
            $this->log->notice(
                $fetch->httpStatus . ' (redirection non résolue, à revérifier) : ' . $url,
                ['stats' => 'externHttpErrorLogic.3xx']
            );
            if ($pageTitle !== null) {
                $this->linkCheckRepository->recordFailure(
                    $pageTitle,
                    $url,
                    $registrableDomain,
                    $fetch->httpStatus,
                    null,
                    ExternLinkCheckVerdict::TransientError
                );
            }

            return $url;
        }

        if (in_array($fetch->httpStatus, self::TRANSIENT_ERROR_STATUSES, true)) {
            // pas de {lien brisé} sur une seule observation : 429/503 sont des limitations
            // temporaires (rate-limit, maintenance globale du serveur). On enregistre et on
            // revérifiera dans RECHECK_DELAY plutôt que de conclure trop vite (voir ExternLinkCheckRepository).
            $this->log->notice($fetch->httpStatus . ' (transitoire, à revérifier) : ' . $url, ['stats' => 'externHttpErrorLogic.' . $fetch->httpStatus]);
            if ($pageTitle !== null) {
                $this->linkCheckRepository->recordFailure(
                    $pageTitle,
                    $url,
                    $registrableDomain,
                    $fetch->httpStatus,
                    null,
                    ExternLinkCheckVerdict::TransientError
                );
            }

            return $url;
        }

        if (self::LOOSE && $fetch->errorKind !== null && in_array($fetch->errorKind, self::LOOSE_ERROR_KINDS_TREATED_AS_DEAD, true)) {
            $this->log->notice(
                'Network failure treated as dead link: ' . $fetch->errorKind->name,
                ['stats' => 'externHttpErrorLogic.' . strtolower($fetch->errorKind->name)]
            );

            return $this->deadLinkTransformer->formatFromUrl($url, summary: $summary);
        }

        // DEFAULT (not filtered) : timeout, TLS error, too-many-redirects, ProxyFailure (SOCKS5)...
        // pas de {lien brisé} : peut-être temporaire, et on n'a pas encore de mécanisme
        // de re-vérification différée pour ces cas-là (cf. §9.6, limité aux statuts ci-dessus)
        $this->log->notice(
            'erreur non gérée sur extractWebData: "' . $this->describeFetchFailure($fetch) . "\" URL: " . $url,
            ['stats' => 'externHttpErrorLogic.defaultSkip']
        );

        return $url;
    }

    private function describeFetchFailure(FetchResult $fetch): string
    {
        if ($fetch->httpStatus !== null) {
            return (string)$fetch->httpStatus;
        }
        if ($fetch->errorKind !== null) {
            return $fetch->errorKind->name . ' (' . ($fetch->rawErrorMessage ?? '') . ')';
        }

        return $fetch->rawErrorMessage ?? 'unknown';
    }
}
