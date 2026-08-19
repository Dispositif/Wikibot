<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink\Validators;

use App\Domain\ExternLink\LinkVerdict;
use App\Domain\ExternLink\WikiwixUrl;
use App\Infrastructure\Monitor\NullLogger;
use Psr\Log\LoggerInterface;

/**
 * The web archive answered HTTP 200 with something that is NOT the archived page, so no
 * citation can be built on its metadata. Known answers :
 *
 *  - Wikiwix (2026-08-19) : the viewer's own React shell — <title>Wikiwix
 *    Archives</title>, lang="en", <div id="root"> — served when the snapshot is loaded
 *    client-side instead (see WikiwixContentResolver, which exists to avoid landing
 *    here) ; left alone it produced citations reading "|langue=en |titre=Wikiwix
 *    Archives", pure fiction. Also a soft 404 for a URL Wikiwix never archived :
 *    <title>Wikiwix Archive - Unknown page</title> over an <h1>404</h1>, which turned
 *    into "|titre=Unknown page".
 *  - Internet Archive (2026-08-19) : its own JS "player" shell on the default memento
 *    URL — <title>Wayback Machine</title>, toolbar + an iframe that loads the real
 *    snapshot client-side instead of serving it directly (a rollout, not consistent :
 *    the same URL sometimes answers with the real content). Left alone it turned an
 *    existing {{Lien brisé}} into a citation titled "Wayback Machine". Title-only : an
 *    earlier version also matched on the toolbar's own markup ("BEGIN WAYBACK TOOLBAR
 *    INSERT", "wm-ipp-base"), which turned out to be present on EVERY archive.org page,
 *    including a fully-rendered one with real content — archive.org injects that
 *    toolbar universally, it isn't specific to the broken shell. Reported false-positive
 *    2026-08-19 (radio-canada.ca : real 124KB archived page rejected as "no content").
 *
 * Verdict is KeepUrlAsIs, not TreatAsDead : "the archiver has no usable snapshot" says
 * nothing about the source link, and the codebase deliberately never puts {{lien brisé}}
 * on an archive URL (DeadLinkTransformer::formatFromUrl).
 *
 * Deliberately host-scoped : these markers are generic enough (a bare CRA shell, the
 * words "unknown page", the words "wayback machine") that checking them on any domain
 * would eventually misfire.
 */
class ArchiveNoContentValidator implements LinkGateInterface
{
    private const NO_CONTENT_TITLE_PATTERNS = [
        '#^wikiwix archives$#iu',
        '#unknown page#iu', // "Wikiwix Archive - Unknown page", prefix-stripped by ExternPage
        '#^wayback machine$#iu', // Internet Archive's own player shell, not the archived page
    ];

    /** each rule's markers must ALL be present : a CRA shell alone isn't proof of anything */
    private const NO_CONTENT_BODY_RULES = [
        'viewer shell' => [
            'You need to enable JavaScript to run this app.',
            '<div id="root">',
            'Wikiwix Archives',
        ],
        'unknown page' => [
            'Wikiwix Archive - Unknown page',
        ],
    ];

    public function __construct(
        private readonly array           $pageData,
        private readonly string          $url,
        private readonly ?string         $htmlBody = null,
        private readonly LoggerInterface $log = new NullLogger()
    )
    {
    }

    public function check(): LinkVerdict
    {
        if (!WikiwixUrl::isWikiwixUrl($this->url) && !$this->isWebArchiveOrgUrl($this->url)) {
            return LinkVerdict::Accept;
        }

        $reason = $this->noContentReason();
        if ($reason !== null) {
            $this->log->notice(
                'Web archive served no archived content (' . $reason . ') : ' . $this->url,
                ['stats' => 'externref.skip.archiveNoContent']
            );

            return LinkVerdict::KeepUrlAsIs;
        }

        return LinkVerdict::Accept;
    }

    private function isWebArchiveOrgUrl(string $url): bool
    {
        return str_starts_with($url, 'http://web.archive.org/web/')
            || str_starts_with($url, 'https://web.archive.org/web/');
    }

    private function noContentReason(): ?string
    {
        $title = trim((string)($this->pageData['meta']['html-title'] ?? ''));
        foreach (self::NO_CONTENT_TITLE_PATTERNS as $pattern) {
            if ($title !== '' && preg_match($pattern, $title) === 1) {
                return 'title';
            }
        }

        if ($this->htmlBody === null || $this->htmlBody === '') {
            return null;
        }
        foreach (self::NO_CONTENT_BODY_RULES as $name => $markers) {
            $allPresent = true;
            foreach ($markers as $marker) {
                if (!str_contains($this->htmlBody, $marker)) {
                    $allPresent = false;
                    break;
                }
            }
            if ($allPresent) {
                return $name;
            }
        }

        return null;
    }
}
