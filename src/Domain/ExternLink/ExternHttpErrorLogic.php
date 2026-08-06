<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink;

use App\Infrastructure\Monitor\NullLogger;
use Psr\Log\LoggerInterface;

/**
 * Doc : https://developer.mozilla.org/fr/docs/Web/HTTP/Status/503
 *
 * Behaviorally identical to the previous regex-on-exception-message version : same
 * status codes routed to the same outcomes. Only the input changed, from a raw
 * exception message to a typed FetchResult (see ExternPageFactory::fetch()).
 */
class ExternHttpErrorLogic
{
    final public const LOG_REQUEST_ERROR = __DIR__ . '/../../Application/resources/external_request_error.log';
    protected const LOOSE = true;

    /** @var int[] statuses treated as a dead link only because LOOSE is enabled (server/gateway errors) */
    private const LOOSE_STATUSES_TREATED_AS_DEAD = [500, 502];

    /** @var FetchErrorKind[] network failures treated as a dead link only because LOOSE is enabled */
    private const LOOSE_ERROR_KINDS_TREATED_AS_DEAD
        = [
            FetchErrorKind::EmptyReply,
            FetchErrorKind::DnsResolutionFailed,
            FetchErrorKind::ProxyFailure,
        ];

    public function __construct(
        protected DeadLinkTransformer    $deadLinkTransformer,
        private readonly LoggerInterface $log = new NullLogger()
    )
    {
    }

    public function manageByFetchResult(FetchResult $fetch): string
    {
        $url = $fetch->requestedUrl;

        if ($fetch->httpStatus === 410) {
            $this->log->notice('410 Gone', ['stats' => 'externHttpErrorLogic.410']);

            return ExternRefTransformer::REPLACE_410 ? $this->deadLinkTransformer->formatFromUrl($url) : $url;
        }
        if ($fetch->httpStatus === 404) {
            $this->log->notice('404 Not Found', ['stats' => 'externHttpErrorLogic.404']);

            return ExternRefTransformer::REPLACE_404 ? $this->deadLinkTransformer->formatFromUrl($url) : $url;
        }
        if ($fetch->httpStatus === 400) {
            $this->log->warning('400 Bad Request : ' . $url, ['stats' => 'externHttpErrorLogic.400']);

            return $url;
        }
        if ($fetch->httpStatus === 403) {
            $this->log->warning('403 Forbidden : ' . $url, ['stats' => 'externHttpErrorLogic.403']);
            // TODO return blankLienWeb without consulté le=...

            return $url;
        }
        if ($fetch->httpStatus === 401) {
            $this->log->notice('401 Unauthorized : skip ' . $url, ['stats' => 'externHttpErrorLogic.401']);

            return $url;
        }

        if (self::LOOSE && in_array($fetch->httpStatus, self::LOOSE_STATUSES_TREATED_AS_DEAD, true)) {
            $this->log->notice($fetch->httpStatus . ' server error', ['stats' => 'externHttpErrorLogic.' . $fetch->httpStatus]);

            return $this->deadLinkTransformer->formatFromUrl($url);
        }

        if (self::LOOSE && $fetch->errorKind !== null && in_array($fetch->errorKind, self::LOOSE_ERROR_KINDS_TREATED_AS_DEAD, true)) {
            $this->log->notice(
                'Network failure treated as dead link: ' . $fetch->errorKind->name,
                ['stats' => 'externHttpErrorLogic.' . strtolower($fetch->errorKind->name)]
            );

            return $this->deadLinkTransformer->formatFromUrl($url);
        }

        // DEFAULT (not filtered) : 429, 503, 451, timeout, TLS error, too-many-redirects...
        // pas de {lien brisé} : peut-être temporaire, et on n'a pas encore de mécanisme
        // de re-vérification différée (cf. docs/audit-gestion-erreurs-crawl-2026-08.md §9.6)
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