<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink;

/**
 * Wikiwix cache URLs : recognition, extraction of the archived (original) URL and
 * HTTPS canonicalization.
 *
 * Two flavours are found in articles :
 *  - query : "http://wikiwix.com/cache/?url=<original>" (also archive.wikiwix.com,
 *    with or without "index2.php", with the original URL raw or percent-encoded) ;
 *  - longform : "https://archive.wikiwix.com/cache/<timestamp>/<original>", what
 *    WikiwixAdapter returns.
 *
 * The plain-HTTP query forms are served as-is by Wikiwix (no redirect to HTTPS, checked
 * 2026-08-19), so nothing upgrades them for the reader : only "https://wikiwix.com/..."
 * redirects, and to "https://archive.wikiwix.com/cache/index2.php?url=..." -- hence the
 * canonical form used here.
 */
final class WikiwixUrl
{
    public const ARCHIVER_WIKILINK = '[[Wikiwix]]';

    private const CANONICAL_QUERY_PREFIX = 'https://archive.wikiwix.com/cache/index2.php?url=';
    private const CANONICAL_LONGFORM_PREFIX = 'https://archive.wikiwix.com/cache/';

    private const HOST_PATTERN = '#^https?://(?:[\w-]+\.)*wikiwix\.com(?:[:/?]|$)#i';
    /** lazy "(...&)*?" so an original URL carrying its own "url=" param can't win over Wikiwix's */
    private const QUERY_PATTERN = '#^https?://(?:[\w-]+\.)*wikiwix\.com/cache/(?:index2\.php)?\?(?:[^&]*&)*?url=(?<original>.+)$#i';
    private const LONGFORM_PATTERN = '#^https?://(?:[\w-]+\.)*wikiwix\.com/cache/(?<timestamp>\d{4,})/(?<original>.+)$#i';

    public static function isWikiwixUrl(string $url): bool
    {
        return (bool)preg_match(self::HOST_PATTERN, $url);
    }

    /**
     * The (deterministic, no network call) reader-facing viewer URL for an original URL
     * -- what WikiwixAdapter itself builds internally before its handshake. Exposed here
     * (2026-08-20) so LiveLinkArchiveEnricher can construct a Wikiwix candidate directly
     * instead of going through WikiwixAdapter::searchWebarchive(), which does its own
     * internal resolve+fetch+validate cycle just to answer "does a snapshot exist" --
     * redundant work when the caller is about to resolve+fetch+validate that same URL
     * again anyway for content comparison.
     */
    public static function buildViewerUrl(string $originalUrl): string
    {
        return self::CANONICAL_QUERY_PREFIX . rawurlencode($originalUrl);
    }

    /**
     * The archived URL, verbatim (percent-encoded or not, trailing Wikiwix params such as
     * "&title=" kept) : it's an opaque value here, only meant to be handed back to Wikiwix.
     */
    public static function extractOriginalUrl(string $url): ?string
    {
        if (preg_match(self::LONGFORM_PATTERN, $url, $matches) === 1) {
            return $matches['original'];
        }
        if (preg_match(self::QUERY_PATTERN, $url, $matches) === 1) {
            return $matches['original'];
        }

        return null;
    }

    /**
     * Host of the archived site, e.g. "www.ign.fr" -- what |site= should name instead of
     * the archiver.
     */
    public static function extractOriginalHost(string $url): ?string
    {
        $original = self::extractOriginalUrl($url);
        if ($original === null) {
            return null;
        }

        $host = parse_url(self::decodeIfEncoded($original), PHP_URL_HOST);

        return (is_string($host) && $host !== '') ? strtolower($host) : null;
    }

    /**
     * HTTPS canonical form, for the reader's security. Unrecognized Wikiwix URLs are
     * returned untouched : blindly switching the scheme on a shape we can't parse would
     * risk pointing at a page that doesn't answer.
     */
    public static function toSecureUrl(string $url): string
    {
        if (preg_match(self::LONGFORM_PATTERN, $url, $matches) === 1) {
            return self::CANONICAL_LONGFORM_PREFIX . $matches['timestamp'] . '/' . $matches['original'];
        }
        if (preg_match(self::QUERY_PATTERN, $url, $matches) === 1) {
            return self::CANONICAL_QUERY_PREFIX . $matches['original'];
        }

        return $url;
    }

    private static function decodeIfEncoded(string $url): string
    {
        if (preg_match('#^https?%3A#i', $url) === 1) {
            return urldecode($url);
        }
        // scheme-less archived URL ("www.ign.fr/page") : parse_url() would read it as a path
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) !== 1) {
            return '//' . ltrim($url, '/');
        }

        return $url;
    }
}
