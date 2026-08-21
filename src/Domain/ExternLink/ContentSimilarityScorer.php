<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2026 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink;

use App\Domain\Utils\SimHash;
use App\Domain\Utils\TextUtil;

/**
 * Scores how likely a live page and an archived snapshot of the same URL carry the SAME
 * content, on a 0-100 scale. Pure text comparison, no I/O -- callers (LiveLinkArchiveEnricher)
 * own the fetching and the accept/review/reject thresholds.
 *
 * Two independent signals, combined :
 *  - <title> similarity : cheap, and a title surviving unchanged across a redesign is a
 *    strong (if not sufficient alone) signal.
 *  - SimHash on the body's shingled text : tolerant of small injected differences (cookie
 *    banner, ads, a "last updated" timestamp) that would make a byte-exact/cryptographic
 *    hash comparison useless between a live page and any archive of it.
 * A length-ratio floor runs first and short-circuits to 0 : a soft-404/interstitial/paywall
 * variant of the same URL can still share a plausible title with the real page, so title
 * similarity alone can't be trusted to catch that case.
 */
final class ContentSimilarityScorer
{
    /** Below this body-length ratio, treat as unrelated regardless of title/text similarity. */
    private const MIN_LENGTH_RATIO = 0.25;
    private const TITLE_WEIGHT = 0.3;
    private const TEXT_WEIGHT = 0.7;

    public function score(string $liveHtml, string $archiveHtml): float
    {
        $liveText = self::extractText($liveHtml);
        $archiveText = self::extractText($archiveHtml);

        if (self::lengthRatio($liveText, $archiveText) < self::MIN_LENGTH_RATIO) {
            return 0.0;
        }

        $titleSim = self::titleSimilarity(self::extractTitle($liveHtml), self::extractTitle($archiveHtml));
        $textSim = self::textSimilarity($liveText, $archiveText);

        return self::TITLE_WEIGHT * $titleSim + self::TEXT_WEIGHT * $textSim;
    }

    private static function extractTitle(string $html): string
    {
        if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m) !== 1) {
            return '';
        }

        return trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * Deliberately basic (no readability-style main-content extraction) : script/style
     * blocks are stripped so their code doesn't pollute the text signal, everything else
     * (nav, footer, ads) is left in on both sides -- the comparison is symmetric, so
     * boilerplate present on both the live page and its own archive washes out rather
     * than skewing the score.
     */
    private static function extractText(string $html): string
    {
        $stripped = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($stripped), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private static function lengthRatio(string $a, string $b): float
    {
        $lenA = mb_strlen($a);
        $lenB = mb_strlen($b);
        if ($lenA === 0 || $lenB === 0) {
            return 0.0;
        }

        return min($lenA, $lenB) / max($lenA, $lenB);
    }

    private static function titleSimilarity(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }
        similar_text(self::normalize($a), self::normalize($b), $percent);

        return $percent;
    }

    private static function textSimilarity(string $a, string $b): float
    {
        $distance = SimHash::hammingDistance(SimHash::fingerprint($a), SimHash::fingerprint($b));

        return (64 - $distance) / 64 * 100;
    }

    private static function normalize(string $value): string
    {
        return mb_strtolower(TextUtil::stripAccents(trim($value)));
    }
}
