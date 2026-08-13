<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink\Raw;

use App\Domain\ExternLink\Raw\Hints\FrenchDate;
use App\Domain\Utils\TextUtil;

/**
 * Strips from the leftover manuscript text ("residue", what no Hints/ extractor could
 * attribute to a field) everything that merely restates data already captured in the
 * template -- title words, site name, domain, author names, the date -- plus relational
 * stop words. What survives is genuinely new information ; when NOTHING survives, the
 * residue was pure redundancy and there's no reason to keep it as a 'citation' at all.
 *
 * This is the "extraction par soustraction" of the old ParseRawOuvrage POC, but turned
 * into a VERIFICATION rather than a guess : instead of positively identifying "Witbank
 * News, 1er novembre 2018" as a site+date (ambiguous, a bare name looks like anything),
 * it just checks that every word is already explained by the merged data. That's why it
 * can safely clear residues the extractor chain deliberately refuses to touch.
 *
 * Reconstruction keeps the ORIGINAL text between the first and last surviving word --
 * never surgically removes from the middle, which would mangle readable prose into
 * word soup. So "Biographie Le Maitron d'Henri Lefebvre" (title "LEFEBVRE Henri,
 * François", site "maitron.fr") reduces to "Biographie", but a residue with new content
 * on both ends keeps whatever sits between them verbatim.
 */
final class ResidueReducer
{
    /** Domain substring matching (see isDomainFragment()) needs a floor : without one,
     *  2-3 letter words would match almost any host by accident. */
    private const MIN_DOMAIN_FRAGMENT_LENGTH = 4;

    /** Relational words : never content on their own, safe to treat as redundant. */
    private const STOP_WORDS
        = [
            'de', 'du', 'des', 'd', 'le', 'la', 'les', 'l', 'un', 'une', 'au', 'aux', 'a',
            'en', 'et', 'ou', 'sur', 'pour', 'par', 'avec', 'dans', 'chez', 'vers', 'depuis',
            'ce', 'cet', 'cette', 'ces', 'son', 'sa', 'ses', 'leur', 'leurs', 'notre', 'nos',
            'votre', 'vos', 'mon', 'ma', 'mes', 'qui', 'que', 'dont', 'est', 'sont',
            'the', 'of', 'on', 'in', 'at', 'for', 'with', 'and', 'by', 'from', 'to', 'an',
        ];

    /** Citation boilerplate : "sur le site de X", "page consultée", "lire en ligne"...
     *  Deliberately short -- words like "article" or "source" can be real content. */
    private const FILLER_WORDS
        = [
            'site', 'page', 'voir', 'cf', 'lire', 'ligne', 'disponible',
            'consulte', 'consultee', 'consultes', 'publie', 'publiee', 'mis', 'jour',
        ];

    private const WORD_PATTERN = '#[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*#u';

    /**
     * Template params whose value is human-readable text worth subtracting word by word.
     * 'date'/'consulté le' are handled separately (dateTokens(), format-aware).
     */
    private const TEXT_PARAMS
        = [
            'titre', 'site', 'périodique', 'éditeur', 'auteur', 'auteurs', 'auteur1',
            'auteur2', 'auteur3', 'auteur4', 'nom1', 'prénom1', 'traducteur', 'série',
        ];

    private const DATE_PARAMS = ['date', 'consulté le'];

    /**
     * @param array<string, string> $mapData merged template params (HintMerger's output)
     * @return string|null null when the residue was entirely redundant
     */
    public function reduce(string $residue, array $mapData, ?string $url = null): ?string
    {
        if (trim($residue) === '') {
            return null;
        }

        if (preg_match_all(self::WORD_PATTERN, $residue, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return null; // punctuation only
        }

        $knownTokens = $this->buildKnownTokens($mapData);
        $domainHaystack = $this->buildDomainHaystack($mapData, $url);

        $firstKept = null;
        $lastKept = null;
        foreach ($matches[0] as $match) {
            [$word, $offset] = $match;
            if ($this->isRedundant($word, $knownTokens, $domainHaystack)) {
                continue;
            }
            $firstKept ??= $offset;
            $lastKept = $offset + strlen($word);
        }

        if ($firstKept === null) {
            return null;
        }

        $kept = trim(substr($residue, $firstKept, $lastKept - $firstKept), " \t\n\r\0\x0B,;.:-–—()[]«»\"'");
        if ($kept === '') {
            return null;
        }

        // The span ends at the last surviving WORD, which can fall inside wiki markup
        // and cut it open ("{{p.|173-209" without its closing braces) -- that would
        // corrupt the rendered page. Rather than guess where the markup ends, keep the
        // residue as it came : trimming is a nicety, valid wikitext is not.
        return $this->isMarkupBalanced($kept) ? $kept : $residue;
    }

    private function isMarkupBalanced(string $text): bool
    {
        return substr_count($text, '{{') === substr_count($text, '}}')
            && substr_count($text, '[[') === substr_count($text, ']]');
    }

    /**
     * @param string[] $knownTokens
     */
    private function isRedundant(string $word, array $knownTokens, string $domainHaystack): bool
    {
        $normalized = $this->normalize($word);
        if ($normalized === '') {
            return true;
        }
        if (in_array($normalized, self::STOP_WORDS, true) || in_array($normalized, self::FILLER_WORDS, true)) {
            return true;
        }
        if (in_array($normalized, $knownTokens, true)) {
            return true;
        }
        if ($this->isDomainFragment($normalized, $domainHaystack)) {
            return true;
        }

        // "Désiré-Clitandre" : redundant only if EVERY hyphen part is itself redundant,
        // so a compound half-matching a known value isn't dropped wholesale.
        if (str_contains($normalized, '-')) {
            foreach (explode('-', $normalized) as $part) {
                if ($part !== '' && !$this->isRedundant($part, $knownTokens, $domainHaystack)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    private function isDomainFragment(string $normalized, string $domainHaystack): bool
    {
        return $domainHaystack !== ''
            && mb_strlen($normalized) >= self::MIN_DOMAIN_FRAGMENT_LENGTH
            && str_contains($domainHaystack, $normalized);
    }

    /**
     * @param array<string, string> $mapData
     * @return string[]
     */
    private function buildKnownTokens(array $mapData): array
    {
        $tokens = [];
        foreach (self::TEXT_PARAMS as $param) {
            if (empty($mapData[$param])) {
                continue;
            }
            foreach ($this->words((string) $mapData[$param]) as $word) {
                $tokens[] = $this->normalize($word);
            }
        }
        foreach (self::DATE_PARAMS as $param) {
            if (!empty($mapData[$param])) {
                $tokens = [...$tokens, ...$this->dateTokens((string) $mapData[$param])];
            }
        }

        return array_values(array_unique(array_filter($tokens, static fn($t): bool => $t !== '')));
    }

    /**
     * Every spelling of the same calendar date, so a manuscript "1er novembre 2018" is
     * recognized as redundant with a crawled "01-11-2018" : day/month/year numbers
     * (leading zeros stripped) plus the month's French name, both directions.
     *
     * @return string[]
     */
    private function dateTokens(string $date): array
    {
        $date = trim($date);

        if (preg_match('#^(\d{1,2})[-/.](\d{1,2})[-/.](\d{4})$#', $date, $m) === 1) {
            return $this->dayMonthYearTokens((int) $m[1], (int) $m[2], $m[3]);
        }
        if (preg_match('#^(\d{4})[-/.](\d{1,2})[-/.](\d{1,2})$#', $date, $m) === 1) {
            return $this->dayMonthYearTokens((int) $m[3], (int) $m[2], $m[1]);
        }
        if (preg_match('#^(' . FrenchDate::DAY_PATTERN . ')\s+(' . FrenchDate::MONTHS_PATTERN . ')\s+(\d{4})$#iu', $date, $m) === 1) {
            return $this->dayMonthYearTokens(
                FrenchDate::dayNumber($m[1]),
                FrenchDate::monthNumber($m[2]) ?? 0,
                $m[3]
            );
        }

        return array_map(fn(string $w): string => $this->normalize($w), $this->words($date));
    }

    /**
     * @return string[]
     */
    private function dayMonthYearTokens(int $day, int $month, string $year): array
    {
        $tokens = [(string) $day, (string) $month, $year];
        $monthName = FrenchDate::monthName($month);
        if ($monthName !== null) {
            $tokens[] = $this->normalize($monthName);
        }

        return $tokens;
    }

    /**
     * @param array<string, string> $mapData
     */
    private function buildDomainHaystack(array $mapData, ?string $url): string
    {
        $parts = [];
        if (!empty($mapData['site'])) {
            $parts[] = (string) $mapData['site'];
        }
        if ($url !== null && $url !== '') {
            $parts[] = (string) (parse_url($url, PHP_URL_HOST) ?? '');
        }

        return $this->normalize(preg_replace('#[^\p{L}\p{N}]+#u', '', implode('', $parts)) ?? '');
    }

    /**
     * @return string[]
     */
    private function words(string $text): array
    {
        return preg_match_all(self::WORD_PATTERN, $text, $m) === 0 ? [] : $m[0];
    }

    /**
     * Lowercase, accent-free, with the French ordinal suffix dropped so "1er" and "01"
     * compare equal, and leading zeros stripped on plain numbers.
     */
    private function normalize(string $value): string
    {
        $normalized = mb_strtolower(TextUtil::stripAccents(trim($value)));

        if (preg_match('#^(\d+)(?:er|ère|eme|e|nd|nds|s)?$#u', $normalized, $m) === 1) {
            return (string) (int) $m[1];
        }

        return $normalized;
    }
}
