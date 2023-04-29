<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Utils;

/**
 * Class TextUtil.
 */
abstract class TextUtil
{
    public const SYMBOL_TEXT_CUT = '…';

    public const SKIP_PREDICT_PARAM = ['issue'];

    public const NO_BREAK_SPACE = "\xC2\xA0"; // &#160;

    public const NO_BREAK_THIN_SPACE = "\xE2\x80\xAF";

    /** TODO ? add '-' and '.' ???  */
    public const ALL_PUNCTUATION
        = [
            '!',
            '"',
            '«',
            '»',
            '#',
            '$',
            '%',
            "'",
            '’',
            '´',
            '`',
            '^',
            '…',
            '‽',
            '(',
            ')',
            '*',
            '⁂',
            '+',
            '–',
            '—',
            '/',
            ':',
            ';',
            '?',
            '@',
            '[',
            '\\',
            ']',
            '_',
            '`',
            '{',
            '|',
            '¦',
            '}',
            '~',
            '<',
            '>',
            '№',
            '©',
            '®',
            '°',
            '†',
            '§',
            '∴',
            '∵',
            '¶',
            '•',
            '+',
        ];

    // &#8239;
    //    const ELLIPSIS = '…';
    //    const LAQUO = '«'; // &laquo;
    //    const RAQUO = '»'; // &raquo;
    //    const RSQUO = '’'; // &rsquo;
    //    const TIMES = '×'; // &times;
    //    const NDASH = '–'; // &ndash; or &#x2013;
    //    const MDASH = '—'; // &mdash; or &#x2014;
    //    const LDQUO = '“'; // &ldquo; or &#8220;
    //    const RDQUO = '”'; // &rdquo; or &#8221;
    //    const BDQUO = '„'; // &bdquo; or &#8222;
    //    const SHY = "\xC2\xAD"; // &shy;
    //    const TRADE = '™'; // &trade;
    //    const REG = '®'; // &reg;
    //    const COPY = '©'; // &copy;
    public const ALL_SPACES = "\xE2\x80\xAF|\xC2\xAD|\xC2\xA0|\\s"; // Used in regexps. Better than \s

    /**
     * UTF8 first letter in upper case.
     * "économie" => "Économie".
     */
    public static function mb_ucfirst(string $str, ?string $e = 'UTF-8'): string
    {
        $first = mb_strtoupper(mb_substr($str, 0, 1, $e), $e);
        $rest = mb_substr($str, 1, mb_strlen($str, $e), $e);

        return $first.$rest;
    }

    /**
     * UTF8 first letter in lower case.
     * "Économie" => "économie".
     *
     *
     * @return string
     */
    public static function mb_lowerfirst(string $str, ?string $e = 'UTF-8'): string
    {
        $first = mb_strtolower(mb_substr($str, 0, 1, $e), $e);
        $rest = mb_substr($str, 1, mb_strlen($str, $e), $e);

        return $first.$rest;
    }

    public static function replaceNonBreakingSpaces(string $text): string
    {
        return str_replace([self::NO_BREAK_SPACE, self::NO_BREAK_THIN_SPACE], ' ', $text);
    }

    /**
     * Trim also non-breaking space and carriage return.
     */
    public static function trim(string $string): string
    {
        return trim($string, self::NO_BREAK_SPACE.self::NO_BREAK_THIN_SPACE."\n\t\r");
    }

    /**
     * Todo verify/correct.
     *
     * @param string $str
     *
     * @return bool
     */
    //    static public function containsNonLatinCharacters(string $str): bool
    //    {
    //        return preg_match('/[^\\p{Common}\\p{Latin}]/u', $str);
    //    }
    /**
     * Simplest levenshtein distance prediction of the correct param name.
     * Weird results with ASCII extended chars :
     * levenshtein('notre','nôtre') => 2
     * TODO move.
     *
     * @param int    $max Maximum number of permutation/add/subtraction)
     *
     * @return string|null
     */
    public static function predictCorrectParam(string $str, array $names, int $max = 2): ?string
    {
        $sanitized = self::sanitizeParamForPredict($str);
        $closest = null;
        foreach ($names as $name) {
            $sanitizedName = self::sanitizeParamForPredict($name);
            if ($str === $name || $sanitized === $sanitizedName) {
                return $name; // exact match
            }
            $lev = levenshtein($str, $name);
            $lev2 = levenshtein($sanitized, $sanitizedName);

            if (!isset($shortest) || $lev < $shortest || $lev2 < $shortest) {
                $closest = $name;
                $shortest = $lev;
            }
        }
        if (isset($shortest) && $shortest <= $max && !in_array($sanitized, self::SKIP_PREDICT_PARAM)) {
            return $closest;
        }

        return null;
    }

    /**
     * For predictCorrectParam().
     *
     *
     * @return string
     */
    private static function sanitizeParamForPredict(string $str): string
    {
        $sanitized = mb_strtolower(self::stripPunctuation(self::stripAccents($str)));

        return trim(preg_replace('#[^a-z0-9 ]#', '', $sanitized));
    }

    /**
     * Strip punctuation
     * UTF-8 compatible ??
     * Note : can't use str_split() which cut on 1 byte length
     * See http://fr.wikipedia.org/wiki/Ponctuation.
     */
    public static function stripPunctuation(string $str): string
    {
        return str_replace(
            self::ALL_PUNCTUATION,
            '',
            $str
        );
    }

    /**
     * Strip accents
     * OK : grec, cyrillique, macron, hatchek, brève, rond en chef, tilde
     * UTF-8 compatible.
     *
     *
     * @return string
     */
    public static function stripAccents(string $string): string
    {
        return strtr(
            utf8_decode($string),
            utf8_decode(
                'àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝøāǟǡēḕḗḡḹīōȫȭȱṑṓǭṝūǖṻȳǣӣᾱῑῡčšžйўŭăӗğÅåůẘẙ'
            ),
            utf8_decode(
                'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUYoaaaeeeglioooooooruuuyæиαιυcszиyuaegAauwy'
            )
        );
    }

    /**
     * Like PHP8 str_ends_with(). Multibytes ok.
     *
     *
     * @return bool
     */
    public static function str_ends_with(string $haystack, string $needle): bool
    {
        $len = mb_strlen($needle);
        if ($len === 0) {
            return true;
        }

        return (mb_substr($haystack, -$len) === $needle);
    }

    /**
     * Like PHP8 str_starts_with().
     *
     *
     * @return bool
     */
    public static function str_starts_with(string $haystack, string $needle): bool
    {
        $len = mb_strlen($needle);

        return (mb_substr($haystack, 0, $len) === $needle);
    }

    /**
     * Cut string at position of last space before Xth character.
     */
    public static function cutTextOnSpace(string $text, int $maxLength = 70): string
    {
        if (mb_strlen($text) > $maxLength) {
            $spacePos = mb_strrpos(mb_substr($text, 0, $maxLength), ' ');
            $spacePos = ($spacePos > ($maxLength - 12)) ? $spacePos : $maxLength;
            $text = trim(mb_substr($text, 0, $spacePos)) . self::SYMBOL_TEXT_CUT;
        }

        return $text;
    }

    public static function countAllCapsWords(string $text): int
    {
        $words = explode(' ', $text);
        $count = 0;
        foreach ($words as $word) {
            if (mb_strlen($word) > 2 && mb_strtoupper($word) === $word) {
                ++$count;
            }
        }

        return $count;
    }
}
