<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink\Raw\Hints;

/**
 * "[url Titre], par NAME" or "[url Titre] par NAME" -- ~1.2% of the corpus (found via a
 * full-corpus coverage sweep, 2026-08). Symmetric to AuthorPrefixExtractor (which
 * matches "NAME, [url ...]" BEFORE the bracket) but for the author mentioned AFTER it.
 * Non-anchored like ItalicSiteAfterCommaExtractor/TrailingDateExtractor/
 * ConsulteLeExtractor : only the name span is consumed, whatever trails ("par Lassaad
 * Ben Ahmed, sur ''BEAC'', 14 novembre 2024." -- site/date) is left in $rest for the
 * rest of the chain to pick up (comma-led "sur"/italic mentions still work on what's
 * left ; a name immediately followed by more comma-separated prose that isn't
 * recognized falls through to HintMerger's 'citation' safety net rather than being lost).
 *
 * Placed FIRST in RawExternLinkParser's $rest chain (before SiteMentionExtractor etc.)
 * since "par X" is the leading marker in every real occurrence seen in the corpus.
 */
final class AuthorMentionAfterCommaExtractor implements HintExtractorInterface
{
    private const PATTERN = '#^,?\s*par\s+(' . FrenchName::NAME_PATTERN . ')\s*(.*)$#u';

    /** A trailing "." is part of an initial ("J.") only if it's the sole letter of that
     *  word ; otherwise (family name followed by a sentence-ending period, e.g.
     *  "Valentin.") NAME_PATTERN's own char class swallows it and it must be stripped. */
    private const TRAILING_INITIAL_PATTERN = '#\b[A-ZÀ-Ý]\.$#u';

    public function extract(string $rest): ?HintMatch
    {
        if (trim($rest) === '' || !preg_match(self::PATTERN, $rest, $m)) {
            return null;
        }

        $name = trim($m[1]);
        if (str_ends_with($name, '.') && preg_match(self::TRAILING_INITIAL_PATTERN, $name) !== 1) {
            $name = rtrim(substr($name, 0, -1));
        }

        return new HintMatch('auteur', $name, $m[2] ?? '');
    }
}
