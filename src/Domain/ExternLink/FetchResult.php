<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink;

/**
 * Typed outcome of a single URL fetch attempt, replacing the previous pattern of
 * throwing an Exception and re-parsing its message with regex to recover the HTTP
 * status / error type (see ExternHttpErrorLogic history).
 */
final class FetchResult
{
    public function __construct(
        public readonly string          $requestedUrl,
        public readonly ?string         $finalUrl,
        public readonly ?int            $httpStatus,
        public readonly ?string         $contentType,
        public readonly int             $bodySize,
        public readonly ?string         $body,
        public readonly ?FetchErrorKind $errorKind = null,
        public readonly ?string         $rawErrorMessage = null,
        // Cloudflare's own documented signal for a Challenge Page response, whatever
        // its type : header value is "challenge", set regardless of title wording/locale.
        public readonly ?string         $cfMitigated = null,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->httpStatus === 200 && $this->errorKind === null && $this->body !== null;
    }

    public function wasRedirected(): bool
    {
        return $this->finalUrl !== null && $this->finalUrl !== $this->requestedUrl;
    }

    /**
     * Redirected to the domain root while the original URL had a deeper path
     * (typical "dead link silently sent home" pattern).
     */
    public function redirectedToRoot(): bool
    {
        if (!$this->wasRedirected()) {
            return false;
        }
        $requestedPath = parse_url($this->requestedUrl, PHP_URL_PATH) ?? '';
        if (in_array(trim($requestedPath, '/'), ['', null], true)) {
            return false; // requested URL was already the root : not a "sent home" redirect
        }
        $finalPath = parse_url((string)$this->finalUrl, PHP_URL_PATH) ?? '';

        return in_array($finalPath, ['', '/'], true);
    }
}
