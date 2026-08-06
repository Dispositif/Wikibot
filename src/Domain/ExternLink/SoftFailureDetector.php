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
            '#\bdan\.com\b#i',
            '#\bhugedomains\b#i',
            '#related searches#i', // typical parking-page filler section
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

        if ($this->matchesAny($title, self::PARKING_SIGNATURES)) {
            $score += 3;
        }
        if ($this->matchesAny($title, self::SOFT_404_TITLE_SIGNATURES)) {
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
