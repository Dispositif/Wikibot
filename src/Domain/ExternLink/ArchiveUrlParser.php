<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2026 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink;

use App\Domain\Models\WebarchiveDTO;
use App\Domain\Utils\DateUtil;
use DateTimeImmutable;

/**
 * Turns an 'archive-url' already sitting in an existing citation into the very same
 * WebarchiveDTO a DeadlinkArchiverInterface adapter would have returned for it, so
 * DeadLinkTransformer can try it as candidate #0 instead of searching every archiver
 * from scratch (see DeadLinkTransformer::formatFromUrl()'s $knownArchive).
 *
 * Reusing the DTO rather than special-casing the known archive is what keeps this
 * feature free of any new crawl/validation/labelling logic : candidate #0 goes through
 * generateLienWebFromArchive() like any other, so it gets fetched, run past
 * SoftFailureDetector/ArchiveNoContentValidator/InterstitialPageValidator, and labelled
 * "site via [[archiver]]" by ExternRefTransformer::correctSiteViaWebarchiver() exactly
 * like a searched candidate -- and falls through to a normal archiver search when the
 * snapshot turns out to be unusable.
 *
 * $originalUrl MUST be the citation's own (dead) |url=, never the archive URL : it is
 * what DeadLinkTransformer::externRefProcessOnArchive() derives
 * 'originalRegistrableDomain' from, i.e. what makes |site= read
 * "channel5belize.com via [[Internet Archive]]" instead of a bare "[[Internet Archive]]".
 *
 * All-static, like WikiwixUrl : pure URL parsing, no state, no collaborators.
 */
final class ArchiveUrlParser
{
    /**
     * Literal, not InternetArchiveAdapter::ARCHIVER_NAME : Domain stays
     * Infrastructure-agnostic here, same rationale as DeadLinkTransformer::
     * recordArchiverUsed(), which compares an archiver against these very strings.
     */
    public const INTERNET_ARCHIVE_WIKILINK = '[[Internet Archive]]';

    /**
     * Wayback timestamps run from a bare year to full YYYYMMDDhhmmss. The optional
     * "if_"/"id_"/"js_"/"cs_"… suffix between timestamp and archived URL is a rendering
     * modifier, part of neither value.
     */
    private const WAYBACK_PATTERN = '#^https?://web\.archive\.org/web/(?<timestamp>\d{4,14})(?:[a-z]{2}_)?/(?<original>.+)$#i';

    /**
     * @param string      $archiveUrl       the citation's 'archive-url'/'archiveurl'
     * @param string      $originalUrl      the citation's own (dead) 'url' -- see class docblock
     * @param string|null $archiveDateParam the citation's 'archive-date'/'archivedate', used only
     *                                      when the archive URL itself carries no usable timestamp
     *
     * @return WebarchiveDTO|null null when nothing reusable was recognized (empty input,
     *      blacklisted archive.today, or an archiver this bot has no support for) --
     *      the caller then just searches archivers normally, as it did before.
     */
    public static function parse(string $archiveUrl, string $originalUrl, ?string $archiveDateParam = null): ?WebarchiveDTO
    {
        $archiveUrl = trim($archiveUrl);
        $originalUrl = trim($originalUrl);
        if ($archiveUrl === '' || $originalUrl === '') {
            return null;
        }

        // Blacklisted on frwiki since 2026-02-23 (see DeadLinkTransformer's const
        // docblock) : still recognized, never reused as a source of content.
        if (preg_match('#^https?://' . DeadLinkTransformer::ARCHIVE_TODAY_DOMAINS_REGEX . '/#i', $archiveUrl) === 1) {
            return null;
        }

        $paramDate = ($archiveDateParam === null || trim($archiveDateParam) === '')
            ? null
            : DateUtil::parseTemplateDate($archiveDateParam);

        if (preg_match(self::WAYBACK_PATTERN, $archiveUrl, $matches) === 1) {
            return new WebarchiveDTO(
                self::INTERNET_ARCHIVE_WIKILINK,
                $originalUrl,
                $archiveUrl,
                self::dateFromWaybackTimestamp($matches['timestamp']) ?? $paramDate
            );
        }

        if (WikiwixUrl::isWikiwixUrl($archiveUrl)) {
            // No date derived from the URL : Wikiwix's "/cache/<n>/" segment is an internal
            // id, not a snapshot timestamp -- same reason LiveArchiveMatch only ever carries
            // an archive date for Wayback. HTTPS-canonicalized here rather than left to
            // ExternRefTransformer::prepareWikiwixUrl() so the DTO already holds the URL
            // that would actually be published.
            return new WebarchiveDTO(
                WikiwixUrl::ARCHIVER_WIKILINK,
                $originalUrl,
                WikiwixUrl::toSecureUrl($archiveUrl),
                $paramDate
            );
        }

        return null;
    }

    /**
     * "20131104044228" => 2013-11-04. Partial stamps shorter than a full YYYYMMDD
     * ("2013", "201311") carry no usable day, so they yield null and the citation's own
     * 'archive-date' is used instead. The round-trip format() check rejects a stamp that
     * only looks like a date ("20131340"), which createFromFormat() would otherwise roll
     * over into a different, valid one -- same guard as DateUtil::parseTemplateDate().
     */
    private static function dateFromWaybackTimestamp(string $timestamp): ?DateTimeImmutable
    {
        if (strlen($timestamp) < 8) {
            return null;
        }

        $ymd = substr($timestamp, 0, 8);
        $date = DateTimeImmutable::createFromFormat('!Ymd', $ymd);

        return ($date instanceof DateTimeImmutable && $date->format('Ymd') === $ymd) ? $date : null;
    }
}
