<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Utils;

class WikiTextUtil extends TextUtil
{
    /**
     * todo {{ref}}
     *
     * @param string $text
     *
     * @return array [0=>['<ref>fu</ref>', 'fu'], 1=> ...]
     */
    public static function extractAllRefs(string $text): array
    {
        // s = "\n" include in "." // m = ^multiline$
        // Exclusion des imbrications
        if (!preg_match_all('#<ref[^>]*>((?:(?!</ref>).)*)</ref>#ism', $text, $refs, PREG_SET_ORDER)) {
            return [];
        }

        return $refs;
    }

    /**
     * remove wiki encoding : italic, bold, links [ ] and [[fu|bar]] => bar
     * replace non-breaking spaces
     * replace {{lang|en|fubar}} => fubar.
     *
     * @param      $text
     * @param bool $stripcomment
     *
     * @return string
     */
    public static function unWikify(string $text, ?bool $stripcomment = true): string
    {
        if (true === $stripcomment) {
            $text = self::removeHTMLcomments($text);
        }

        $text = str_replace(
            ['[', ']', "'''", "''", ' '],
            ['', '', '', '', ' '],
            preg_replace(
                [
                    "#\[\[[^|\]]*\|([^]]*)]]#",
                    '#{{ ?(?:lang|langue) ?\|[^|]+\| ?(?:texte=)?([^{}=]+)(?:\|dir=rtl)?}}#i',
                    "#&[\w\d]{2,7};#",
                ],
                ['$1', '$1', ''],
                $text
            )
        );
        // {{Lien|Jeffrey Robinson}} => Jeffrey Robinson
        $text = preg_replace('#{{ ?lien ?\| ?([^|}]+) ?}}#i', '${1}', $text);

        $text = strip_tags($text, '<sup><sub>');

        return $text;
    }

    public static function isWikify(string $text): bool
    {
        if (self::unWikify($text) !== $text) {
            return true;
        }

        return false;
    }

    /**
     * Generate wikilink from string.
     *
     * @param string      $label
     * @param string|null $page
     *
     * @return string
     */
    public static function wikilink(string $label, ?string $page = null): string
    {
        $label = trim($label);
        $page = trim($page);

        // fu_bar => [[fu_bar]] / Fu, fu => [[fu]]
        if (empty($page) || self::str2WikiTitle($label) === self::str2WikiTitle($page)) {
            return '[['.$label.']]';
        }

        // fu, bar => [[Bar|fu]]
        return sprintf(
            '[[%s|%s]]',
            self::str2WikiTitle($page),
            $label
        );
    }

    /**
     * "fu_bar_ " => "Fu bar".
     */
    private static function str2WikiTitle(string $str): string
    {
        return TextUtil::mb_ucfirst(trim(str_replace('_', ' ', $str)));
    }

    /**
     * Get page titles from wiki encoded links.
     * (but not others projects links like [[wikt:bla]].
     *
     * @param string $text
     *
     * @return array|null
     */
    public static function getWikilinkPages(string $text): ?array
    {
        if (preg_match_all('#\[\[([^:|\]]+)(?:\|[^|\]]*)?]]#', $text, $matches) > 0) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Strip external links (http://) from wiki text.
     * "[http://google.fr Google]" => "Google"
     * "bla [http://google.fr]" => "bla"
     *
     * @param string $text
     *
     * @return string
     */
    public static function stripExternalLink(string $text): string
    {
        $text = preg_replace('#\[(https?://[^][<>\s"]+) *((?<= )[^\n\]]*|)\]#i', '${2}', $text);

        return trim($text);
    }

    /**
     * @param string $text
     *
     * @return bool
     */
    public static function isCommented(string $text): bool
    {
        $text = str_replace('<!-- Paramètre obligatoire -->', '', $text);

        //ou preg_match('#<\!--(?!-->).*-->#s', '', $text); // plus lourd mais précis
        return (preg_match('#<!--[^>]*-->#', $text) > 0) ? true : false;
    }

    /**
     * Remove '<!--', '-->', and everything between.
     * To avoid leaving blank lines, when a comment is both preceded
     * and followed by a newline (ignoring spaces), trim leading and
     * trailing spaces and one of the newlines.
     * (c) WikiMedia /includes/parser/Sanitizer.php.
     *
     * @param string $text
     *
     * @return string
     */
    public static function removeHTMLcomments(string $text)
    {
        while (false !== ($start = mb_strpos($text, '<!--'))) {
            $end = mb_strpos($text, '-->', $start + 4);
            if (false === $end) {
                // Unterminated comment; bail out
                break;
            }
            $end += 3;
            // Trim space and newline if the comment is both
            // preceded and followed by a newline
            $spaceStart = max($start - 1, 0);
            $spaceLen = $end - $spaceStart;
            while (' ' === substr($text, $spaceStart, 1) && $spaceStart > 0) {
                --$spaceStart;
                ++$spaceLen;
            }
            while (' ' === substr($text, $spaceStart + $spaceLen, 1)) {
                ++$spaceLen;
            }
            if ("\n" === substr($text, $spaceStart, 1)
                && "\n" === substr($text, $spaceStart + $spaceLen, 1)
            ) {
                // Remove the comment, leading and trailing
                // spaces, and leave only one newline.
                $text = substr_replace($text, "\n", $spaceStart, $spaceLen + 1);
            } else {
                // Remove just the comment.
                $text = substr_replace($text, '', $start, $end - $start);
            }
        }

        return $text;
    }

    /**
     * Strip the final point (".") as in <ref> ending.
     *
     * @param string $str
     *
     * @return string
     */
    public static function stripFinalPoint(string $str): string
    {
        if (substr($str, -1, 1) === '.') {
            return substr($str, 0, strlen($str) - 1);
        }

        return $str;
    }
}
