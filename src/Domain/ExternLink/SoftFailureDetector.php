<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink;

use App\Domain\ExternLink\Validators\LinkGateInterface;
use App\Infrastructure\Monitor\NullLogger;
use Psr\Log\LoggerInterface;

/**
 * Detect a "soft 404" : the server answers HTTP 200 but the page is not the
 * requested content anymore — homepage redirect, generic error page, parked/for-sale
 * domain. None of these are caught by InterstitialPageValidator (not a bot-challenge)
 * nor by the HTTP status (server insists it's a 200).
 *
 * Scored rather than boolean : each signal is cheap (no extra network call) but
 * individually fallible, so weak signals only trigger combined with another one.
 * Reused unchanged to validate web-archive snapshots (a parked-domain snapshot is
 * just as unusable as a live parked domain) — see DeadLinkTransformer.
 */
class SoftFailureDetector implements LinkGateInterface
{
    private const SCORE_THRESHOLD = 2;
    private const MIN_HTML_BODY_SIZE = 1500;

    private const PARKING_SIGNATURES
        = [
            '#buy this domain#i',
            '#\bis for sale\b#i',
            '#\bmay be for sale\b#i',
            '#parked (free|domain)#i',
            '#courtesy of .{0,40}(sedo|godaddy|dan\.com|namecheap|hugedomains)#i',
            '#\bsedo\.(com|fr)\b#i',
            '#\bsedoparking\.com\b#i', // Sedo's actual parking subdomain -- narrower/safer than bare "sedo.com"
            '#\bdan\.com\b#i',
            '#\bhugedomains\b#i',
            '#related searches#i', // typical parking-page filler section
            // Below : curated from gr3atest/excludeparked (MIT, github.com/gr3atest/
            // excludeparked/blob/master/excludeparked.py)
            '#\bdomain parking\b#i',
            '#\brenew now\b#i',
            '#is owned and listed by#i',
            '#\bsav\.com\b#i',
            '#\bsearchvity\.com\b#i',
            '#\bdomain for sale\b#i',
            '#\bregister4less\b#i',
            '#\baplus\.net\b#i',
            '#\bunited domains\b#i',
            '#this domain has expired#i',
            '#\bdomainpage\.io\b#i',
            '#\bparking-lander\b#i',
            '#this domain name has been registered#i',
            '#\bgandi\.net\b#i',
            // fr : "Ce site web est à vendre !" -- a common French domain-marketplace
            '#\bsite\b.{0,30}\bvendre\b#iu',
            // A very frequent cybersquatting/ad-injection filler on parked site
            '#\bcasino\b#i',
            '#ovh redirect technology#i',
        ];

    private const SOFT_404_TITLE_SIGNATURES
        = [
            '#page (introuvable|non trouv[ée]e?|inexistante)#i',
            '#n\'existe (plus|pas)#i',
            '#erreur 404#i',
            '#404[^0-9]*(not found|error)?$#i',
            '#^(not found|page not found)#i',
            '#has been removed#i',
            '#no longer available#i',
            // Generic app-error-page title shape ("SiteName / Erreur - Portail X"),
            // not tied to any one framework (reported 2026-08-19 on a Wikiwix snapshot
            // of philidor.cmbv.fr, title "kernel (20) / Erreur - Portail PHILIDOR").
            '#/\s*erreur\b#i',
            // A raw upstream gateway error (IIS/ARR-style), no <title> at all -- checked
            // against the body too (see score()), which is the only place this text
            // exists. Reported 2026-08-19, Wikiwix snapshot of rangers.premiumtv.co.uk :
            // body was literally these two lines and nothing else, HTTP 200.
            '#503 service unavailable#i',
            '#no server is available to handle this request#i',
        ];

    public function __construct(
        private readonly FetchResult     $fetch,
        private readonly ?string         $title,
        private readonly LoggerInterface $log = new NullLogger()
    )
    {
    }

    public function check(): LinkVerdict
    {
        if (!$this->fetch->isSuccess()) {
            return LinkVerdict::Accept; // not this gate's job : ExternHttpErrorLogic already handled it
        }

        $score = $this->score();
        if ($score >= self::SCORE_THRESHOLD) {
            $this->log->notice(
                'Soft failure detected (score ' . $score . ') : ' . $this->fetch->requestedUrl,
                ['stats' => 'externref.skip.softFailure']
            );

            return LinkVerdict::TreatAsDead;
        }

        return LinkVerdict::Accept;
    }

    private function score(): int
    {
        $score = 0;
        $title = (string)$this->title;

        // Checked against the body and the URL too, not just the title : a registrar's
        // own splash page (ex: Gandi.net, reported 2026-08-19) often carries no "for
        // sale"/"parked" wording in <title> at all -- just the bare domain, already
        // scored separately by titleIsBareDomain() -- with the actual marketplace text
        // only in the body's visible <h1>/copy. The URL matters separately : a squatted
        // domain redirecting to an ad-injection/spam target (ex: "...casino...") often
        // carries the tell in the redirect destination itself, not in any rendered text.
        $urlText = $this->fetch->requestedUrl . ' ' . (string)$this->fetch->finalUrl;
        if ($this->matchesAny($title, self::PARKING_SIGNATURES)
            || $this->matchesAny((string)$this->fetch->body, self::PARKING_SIGNATURES)
            || $this->matchesAny($urlText, self::PARKING_SIGNATURES)
        ) {
            $score += 3;
        }
        // Checked against the body too, not just the title : a raw upstream gateway
        // error (ex: "503 Service Unavailable\nNo server is available to handle this
        // request.", reported 2026-08-19) can be the entire response with no <title> at
        // all to carry the signal.
        if ($this->matchesAny($title, self::SOFT_404_TITLE_SIGNATURES)
            || $this->matchesAny((string)$this->fetch->body, self::SOFT_404_TITLE_SIGNATURES)
        ) {
            $score += 3;
        }
        if ($this->fetch->redirectedToRoot()) {
            $score += 1;
        }
        if ($this->isAbnormallyShortHtml()) {
            $score += 1;
        }
        if ($this->titleIsBareDomain($title)) {
            $score += 1;
        }

        return $score;
    }

    private function matchesAny(string $subject, array $patterns): bool
    {
        if ($subject === '') {
            return false;
        }
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $subject)) {
                return true;
            }
        }

        return false;
    }

    private function isAbnormallyShortHtml(): bool
    {
        $contentType = (string)$this->fetch->contentType;
        if ($contentType !== '' && !str_contains($contentType, 'html')) {
            return false; // only judge body size for HTML responses
        }

        return $this->fetch->bodySize > 0 && $this->fetch->bodySize < self::MIN_HTML_BODY_SIZE;
    }

    private function titleIsBareDomain(string $title): bool
    {
        if ($title === '') {
            return false;
        }
        $host = parse_url($this->fetch->requestedUrl, PHP_URL_HOST) ?? '';
        $host = preg_replace('#^www\.#i', '', (string)$host);

        return $host !== '' && strcasecmp(trim($title), $host) === 0;
    }
}
