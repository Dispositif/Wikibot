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
        // todo remove HTML tags ?
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
        $text = str_replace(['<small>', '</small>'], '', $text); // ??

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
}
