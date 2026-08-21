<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2026 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Utils;

/**
 * 64-bit locality-sensitive text fingerprint : two near-duplicate texts (same content,
 * different cookie banner/injected ads/timestamp) end up with fingerprints a small
 * Hamming distance apart, unlike a cryptographic hash which flips completely on any
 * byte-level change. Built for ContentSimilarityScorer -- comparing a live page against
 * an archived snapshot of the same URL, where byte-identical content is the exception,
 * not the rule.
 *
 * Memory note : the whole point of reducing a page's text down to one 64-bit int is that
 * nothing bigger needs to be kept around after fingerprint() returns -- callers should
 * discard the source text right after, see LiveLinkArchiveEnricher.
 */
final class SimHash
{
    private const SHINGLE_SIZE = 4; // words per shingle
    private const BITS = 64;

    public static function fingerprint(string $text): int
    {
        $words = self::tokenize($text);
        $shingles = self::shingles($words, self::SHINGLE_SIZE);
        if ($shingles === []) {
            $shingles = $words; // text shorter than one shingle : fall back to bare words
        }
        if ($shingles === []) {
            return 0;
        }

        $weights = array_fill(0, self::BITS, 0);
        foreach ($shingles as $shingle) {
            $hash = self::hash64($shingle);
            for ($bit = 0; $bit < self::BITS; $bit++) {
                $weights[$bit] += ((($hash >> $bit) & 1) === 1) ? 1 : -1;
            }
        }

        $fingerprint = 0;
        for ($bit = 0; $bit < self::BITS; $bit++) {
            if ($weights[$bit] > 0) {
                $fingerprint |= (1 << $bit);
            }
        }

        return $fingerprint;
    }

    public static function hammingDistance(int $a, int $b): int
    {
        $xor = $a ^ $b;
        $count = 0;
        for ($bit = 0; $bit < self::BITS; $bit++) {
            if ((($xor >> $bit) & 1) === 1) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return string[]
     */
    private static function tokenize(string $text): array
    {
        $normalized = mb_strtolower(TextUtil::stripAccents($text));
        preg_match_all('/[\p{L}\p{N}]+/u', $normalized, $matches);

        return $matches[0];
    }

    /**
     * @param string[] $words
     * @return string[]
     */
    private static function shingles(array $words, int $size): array
    {
        $count = count($words);
        if ($count < $size) {
            return [];
        }

        $shingles = [];
        for ($i = 0; $i <= $count - $size; $i++) {
            $shingles[] = implode(' ', array_slice($words, $i, $size));
        }

        return $shingles;
    }

    /**
     * hexdec() on a 16-hex-char (64-bit) hash overflows PHP_INT_MAX into an imprecise
     * float -- unpack('q', ...) reads the raw bytes as a signed 64-bit int instead,
     * exact bit pattern, no precision loss. Sign doesn't matter here : every bitwise op
     * downstream (>>, &, ^, |) operates on the two's-complement pattern regardless.
     */
    private static function hash64(string $value): int
    {
        $binary = hash('fnv1a64', $value, true);

        return unpack('q', $binary)[1];
    }
}
