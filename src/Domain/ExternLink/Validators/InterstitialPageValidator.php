<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink\Validators;

use App\Domain\ValidatorInterface;
use App\Infrastructure\Monitor\NullLogger;
use Psr\Log\LoggerInterface;

/**
 * Detect known bot-challenge/interstitial pages (anti-bot captcha, "please wait"...)
 * returned by the server (HTTP 200) instead of the real content, so their <title>
 * isn't mistakenly used as reference data (ex: "Radware Captcha Page" on aphp.fr).
 */
class InterstitialPageValidator implements ValidatorInterface
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
            '#Checking your browser#i',
            '#DDoS protection by#i',
            '#Unauthorized Request Blocked#i',
        ];

    public function __construct(
        private readonly array           $pageData,
        private readonly string          $url,
        private readonly LoggerInterface $log = new NullLogger()
    )
    {
    }

    public function validate(): bool
    {
        $title = $this->pageData['meta']['html-title'] ?? null;
        if (empty($title)) {
            return false;
        }

        foreach (self::KNOWN_INTERSTITIAL_TITLES as $pattern) {
            if (preg_match($pattern, (string)$title)) {
                $this->log->notice(
                    'Interstitial/bot-challenge page detected ("' . $title . '") : ' . $this->url,
                    ['stats' => 'externref.skip.interstitialPage']
                );

                return true;
            }
        }

        return false;
    }
}
