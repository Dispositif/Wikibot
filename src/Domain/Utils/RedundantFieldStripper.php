<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Utils;

/**
 * Removes, from a text (typically a manuscript 'titre' kept verbatim by a merge like
 * HintMerger's), whatever substrings merely restate OTHER already-resolved field values
 * -- auteur, périodique, date, pages, doi, volume, numéro, éditeur... -- then cleans up
 * the separator debris a removal leaves behind ("Alain Collomp, Titre (Revue Année, p.X)"
 * with auteur/périodique/date/pages known elsewhere reduces to ", Titre ( , )" before
 * final cleanup collapses that further).
 *
 * Deliberately generic (App\Domain\Utils, not ExternLink\Raw) : any worker merging a
 * manuscript text field against separately-resolved metadata can reuse this, not just
 * the raw-extern-link pipeline.
 *
 * Unlike ResidueReducer (a sibling but distinct tool -- that one trims a small LEFTOVER
 * tail from its edges only, word by word, keeping everything between the first and last
 * surviving word verbatim) this operates on a potentially long string and removes
 * matched spans WHEREVER they occur, since the redundant content here isn't confined to
 * one edge (an author byline at the start, a "(Journal Year, p.X-Y)" block further in).
 *
 * Matching is case-INSENSITIVE but deliberately NOT accent-insensitive : offset-based
 * removal needs the normalized string to stay character-for-character aligned with the
 * original, and TextUtil::stripAccents() (round-tripping through the legacy
 * utf8_decode()) doesn't guarantee that for characters outside Latin-1 (curly quotes,
 * em/en dashes...) -- exactly the kind of character a page-range or citation is likely
 * to contain. mb_stripos()/mb_substr() alone are UTF-8-safe with no such risk ; missing
 * an accent-only variant is an acceptable trade-off for not corrupting the title.
 */
final class RedundantFieldStripper
{
    /** A needle shorter than this is never removed, even on a clean word-boundary
     *  match : a bare 1-2 char "numéro"/"volume" value ("3", "32"...) is too likely to
     *  coincide with unrelated content elsewhere in the title (a real "3" that isn't
     *  the article's issue number). Mirrors the spirit of ResidueReducer's own
     *  MIN_DOMAIN_FRAGMENT_LENGTH, slightly more permissive since matches here are
     *  ALSO word-boundary-guarded (see removeOccurrence()), which that class doesn't
     *  need since it already operates word-by-word. */
    private const MIN_NEEDLE_LENGTH = 3;

    private const PAGES_PARAMS = ['pages', 'page'];

    /** Wiki template aliases wrapping a page/page-range value, tried BEFORE the bare
     *  value itself so the whole template shell is removed rather than left empty
     *  ("{{p.|445-477}}" -> nothing, not "{{p.|}}" debris). */
    private const PAGES_TEMPLATE_ALIASES = ['p.', 'p', 'pp.', 'pp'];

    /**
     * @param array<string, string> $knownValues param name => value to strip out of
     *     $text (the caller excludes $text's own source param, e.g. 'titre', plus any
     *     non-content param like 'url')
     */
    public static function strip(string $text, array $knownValues): string
    {
        foreach ($knownValues as $param => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            foreach (self::surfaceForms($param, (string) $value) as $form) {
                $text = self::removeOccurrence($text, $form);
            }
        }

        return self::cleanupSeparators($text);
    }

    /**
     * Candidate literal substrings to search for and remove, most-specific first :
     * - "Nom, Prénom" auteur-style values also try the flipped "Prénom Nom" natural
     *   order a title is far more likely to actually contain.
     * - pages-like values also try common "{{p.|X}}"-style wiki template wrapping.
     * - the bare value itself, and every dash-character variant of it (a page range or
     *   date might use a plain hyphen in one place and an en/em dash in another).
     *
     * @return string[]
     */
    private static function surfaceForms(string $param, string $value): array
    {
        $forms = [];

        if (self::isAuthorParam($param)) {
            $flipped = self::flipAuthorName($value);
            if ($flipped !== null) {
                $forms[] = $flipped;
            }
        }

        if (in_array($param, self::PAGES_PARAMS, true)) {
            foreach (self::dashVariants($value) as $variant) {
                foreach (self::PAGES_TEMPLATE_ALIASES as $alias) {
                    $forms[] = '{{' . $alias . '|' . $variant . '}}';
                }
            }
        }

        return [...$forms, ...self::dashVariants($value)];
    }

    private static function isAuthorParam(string $param): bool
    {
        return preg_match('#^(auteur|traducteur)#u', $param) === 1;
    }

    /** "Collomp, Alain" -> "Alain Collomp" ; only for the simple single-comma "Nom,
     *  Prénom" shape -- anything else (no comma, several) is left alone rather than
     *  guessed at. */
    private static function flipAuthorName(string $value): ?string
    {
        if (preg_match('#^([^,]+),\s*([^,]+)$#u', trim($value), $m) !== 1) {
            return null;
        }

        return trim($m[2]) . ' ' . trim($m[1]);
    }

    /**
     * @return string[]
     */
    private static function dashVariants(string $value): array
    {
        if (preg_match('#[-\x{2013}\x{2014}]#u', $value) !== 1) {
            return [$value];
        }

        return array_values(array_unique([
            $value,
            preg_replace('#[-\x{2013}\x{2014}]#u', '-', $value) ?? $value,
            preg_replace('#[-\x{2013}\x{2014}]#u', "\u{2013}", $value) ?? $value,
        ]));
    }

    /**
     * Removes the FIRST case-insensitive occurrence of $needle from $haystack, unless :
     * - it's shorter than MIN_NEEDLE_LENGTH ;
     * - it isn't on a clean word boundary -- "32" must not match inside "1932", nor
     *   "Annales" inside some longer word that happens to contain it. A needle starting
     *   or ending on punctuation (the "{{p.|445-477}}" template-wrap forms) always
     *   satisfies this trivially, since a brace/pipe/digit boundary already isn't a
     *   letter-or-digit ;
     * - it would leave unbalanced {{ }}/[[ ]] markup behind (a partial match landing
     *   only on the opening or closing half of a template/wikilink).
     * In any of those cases $haystack is returned unchanged rather than risk corrupting it.
     */
    private static function removeOccurrence(string $haystack, string $needle): string
    {
        $needle = trim($needle);
        if (mb_strlen($needle) < self::MIN_NEEDLE_LENGTH) {
            return $haystack;
        }

        $pattern = '#(?<![\p{L}\p{N}])' . preg_quote($needle, '#') . '(?![\p{L}\p{N}])#iu';
        if (preg_match($pattern, $haystack, $m, PREG_OFFSET_CAPTURE) !== 1) {
            return $haystack;
        }

        [$matched, $byteOffset] = $m[0];

        // A match starting with "{{" is one of our own whole-template wrap forms
        // ("{{p.|445-477}}") -- exempt from the argument guard below, since by
        // construction it consumes the full template span, never a fragment of one.
        if (!str_starts_with($matched, '{{') && self::isInsideTemplateArgument($haystack, $byteOffset, strlen($matched))) {
            return $haystack;
        }

        $candidate = substr($haystack, 0, $byteOffset) . substr($haystack, $byteOffset + strlen($matched));

        return self::isMarkupBalanced($candidate) ? $candidate : $haystack;
    }

    private static function isMarkupBalanced(string $text): bool
    {
        return substr_count($text, '{{') === substr_count($text, '}}')
            && substr_count($text, '[[') === substr_count($text, ']]');
    }

    /**
     * "{{s-|XVIII}}" -- removing just "XVIII" would leave "{{s-|}}" behind : still
     * bracket-BALANCED (isMarkupBalanced() only counts delimiters), but a broken/empty
     * template argument, which is arguably worse than leaving the redundant value in
     * place. Detected by checking whether the match sits strictly between an unclosed
     * "{{" on its left and the next "}}" on its right, with no other brace pair between.
     */
    private static function isInsideTemplateArgument(string $haystack, int $byteOffset, int $matchByteLength): bool
    {
        $before = substr($haystack, 0, $byteOffset);
        $after = substr($haystack, $byteOffset + $matchByteLength);

        $lastOpen = strrpos($before, '{{');
        $lastClose = strrpos($before, '}}');
        $insideFromLeft = $lastOpen !== false && ($lastClose === false || $lastOpen > $lastClose);

        $nextClose = strpos($after, '}}');
        $nextOpen = strpos($after, '{{');
        $insideFromRight = $nextClose !== false && ($nextOpen === false || $nextClose < $nextOpen);

        return $insideFromLeft && $insideFromRight;
    }

    /**
     * Collapses the punctuation/whitespace debris left behind by removed spans :
     * repeated separators (", ,"), separator-only parens/brackets ("( , )", "()"),
     * a separator stuck right before another, and doubled spaces. Runs to a fixed
     * point, since one collapse can expose another ("(  , )" needs both the double
     * space AND the empty-parens rule before it fully clears).
     */
    public static function cleanupSeparators(string $text): string
    {
        do {
            $before = $text;
            $text = preg_replace('#([,;:])\s*\1+#u', '$1', $text) ?? $text;
            $text = preg_replace('#\(\s*[,;:\s]*\)#u', '', $text) ?? $text;
            $text = preg_replace('#\[\s*[,;:\s]*\]#u', '', $text) ?? $text;
            // Comma/period only : unlike "!"/"?"/":"/";", French typography WANTS a
            // (often non-breaking) space before those, so stripping it there would
            // itself introduce a typographic error ("dépassées ?" -> "dépassées?").
            $text = preg_replace('#\s+([,.])#u', '$1', $text) ?? $text;
            $text = preg_replace('#[ \t]{2,}#u', ' ', $text) ?? $text;
            $text = trim($text, " \t\n\r,;:");
        } while ($text !== $before);

        return $text;
    }
}
