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
            '#Radware Captcha Page#iu',
            '#Just a moment\.\.\.#iu',
            '#Attention Required#iu',
            '#Access Denied#iu',
            '#Pardon Our Interruption#iu',
            '#Are you a (human|robot)#iu',
            '#V[ée]rifi(cation|ez) que vous [êe]tes (un )?humain#iu',
            '#Please Wait\.\.\. \| Cloudflare#iu',
            '#Just a moment\.\.\.#u', // Cloudflare
            '#One moment, please\.\.\.#iu', // Cloudflare
            '#Checking your browser#iu',
            '#DDoS protection by#iu',
            '#Unauthorized Request Blocked#iu',
            '#Un instant,? s\'il vous pla[iî]t#iu', // fr, ex: fondationlitterairefleurdelys.com, togo-port.net
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
            // ExternPage::parseHtmlTitle() doesn't decode HTML entities, so an interstitial
            // page whose <title> uses entities (ex: "s&#8217;il", "pla&icirc;t...") would
            // otherwise slip past these literal-character patterns.
            $title = html_entity_decode((string)$title);
            foreach (self::KNOWN_INTERSTITIAL_TITLES as $pattern) {
                if (preg_match($pattern, $title)) {
                    $this->logDetection($title);

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
