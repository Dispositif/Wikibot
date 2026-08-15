<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink\Validators;

use App\Domain\ExternLink\LinkVerdict;
use App\Infrastructure\Monitor\NullLogger;
use Psr\Log\LoggerInterface;

/**
 * Detect known bot-challenge/interstitial pages (anti-bot captcha, "please wait"...)
 * returned by the server (HTTP 200) instead of the real content, so their <title>
 * isn't mistakenly used as reference data (ex: "Radware Captcha Page" on aphp.fr).
 *
 * Title matching alone is a losing game: the challenge title is localized per
 * Accept-Language and keeps changing wording (already 2 new Cloudflare variants
 * in as many days). The body markers below are vendor script/asset paths, stable
 * regardless of page language.
 */
class InterstitialPageValidator implements LinkGateInterface
{
    public const KNOWN_INTERSTITIAL_TITLES
        = [
            '#Radware Captcha Page#i',
            '#Just a moment\.\.\.#i',
            '#Attention Required#i',
            '#Access Denied#i',
            '#Pardon Our Interruption#i',
            '#Are you a (human|robot)#i',
            '#V[ée]rifi(cation|ez) que vous [êe]tes (un )?humain#i',
            '#Please Wait\.\.\. \| Cloudflare#i',
            '#Just a moment\.\.\.#', // Cloudflare
            '#One moment, please\.\.\.#i', // Cloudflare
            '#Checking your browser#i',
            '#DDoS protection by#i',
            '#Unauthorized Request Blocked#i',
            '#Un instant,? s\'il vous pla[iî]t#i', // fr, ex: fondationlitterairefleurdelys.com
        ];

    // language-independent, checked against the raw HTML body
    public const KNOWN_INTERSTITIAL_BODY_MARKERS
        = [
            'cdn-cgi/challenge-platform', // Cloudflare
            'challenges.cloudflare.com',
            'cf-turnstile',
            '__cf_chl_rt_tk',
            'window._cf_chl_opt',
        ];

    public function __construct(
        private readonly array           $pageData,
        private readonly string          $url,
        private readonly ?string         $htmlBody = null,
        // Cloudflare's own "cf-mitigated" response header value, see FetchResult::$cfMitigated
        private readonly ?string         $cfMitigated = null,
        private readonly LoggerInterface $log = new NullLogger()
    )
    {
    }

    public function check(): LinkVerdict
    {
        if ($this->cfMitigated === 'challenge') {
            $this->logDetection('cf-mitigated: challenge');

            return LinkVerdict::KeepUrlAsIs;
        }

        $title = $this->pageData['meta']['html-title'] ?? null;

        if (!empty($title)) {
            foreach (self::KNOWN_INTERSTITIAL_TITLES as $pattern) {
                if (preg_match($pattern, (string)$title)) {
                    $this->logDetection((string)$title);

                    return LinkVerdict::KeepUrlAsIs;
                }
            }
        }

        if (!empty($this->htmlBody)) {
            foreach (self::KNOWN_INTERSTITIAL_BODY_MARKERS as $marker) {
                if (str_contains($this->htmlBody, $marker)) {
                    $this->logDetection($marker);

                    return LinkVerdict::KeepUrlAsIs;
                }
            }
        }

        return LinkVerdict::Accept;
    }

    private function logDetection(string $label): void
    {
        $this->log->notice(
            'Interstitial/bot-challenge page detected ("' . $label . '") : ' . $this->url,
            ['stats' => 'externref.skip.interstitialPage']
        );
    }
}
