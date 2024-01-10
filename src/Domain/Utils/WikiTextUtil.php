<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe/Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Utils;

class WikiTextUtil extends TextUtil
{
    /**
     * todo {{ref}}
     *
     *
     * @return array [0=>['<ref>fu</ref>', 'fu'], 1=> ...]
     */
    public static function extractRefsAndListOfLinks(string $text): array
    {
        // s = "\n" include in "." // m = ^multiline$
        // Exclusion des imbrications
        if (!preg_match_all('#<ref[^>/]*>((?:(?!</ref>).)*)</ref>#ism', $text, $refs, PREG_SET_ORDER)) {
            return [];
        }
        $result = $refs;

        // extraction des liens externes
        // ^\* *(https?:\/\/[^ ]+[^ .])$
        if (preg_match_all('#^\* *(https?://[^ \n]+[^ \n.])\.? *\n#im', $text, $liensExternes, PREG_SET_ORDER)) {
            $result = [...$result, ...$liensExternes];
        }

        return $result;
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

        return strip_tags($text, '<sup><sub>');
    }

    public static function isWikify(string $text): bool
    {
        return self::unWikify($text) !== $text;
    }

    /**
     * Generate wikilink from string.
     *
     *
     * @return string
     */
    public static function wikilink(string $label, ?string $page = null): string
    {
        $label = trim(str_replace('_', ' ', self::unWikify($label)));
        $page = ($page) ? trim(self::unWikify($page)) : null;

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
     *
     * @return string
     */
    private static function str2WikiTitle(string $str): string
    {
        return TextUtil::mb_ucfirst(trim(str_replace('_', ' ', $str)));
    }

    /**
     * Get page titles from wiki encoded links.
     * (but not others projects links like [[wikt:bla]].
     *
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
     *
     * @return string
     */
    public static function stripExternalLink(string $text): string
    {
        $text = preg_replace('#\[(https?://[^][<>\s"]+) *((?<= )[^\n\]]*|)\]#i', '${2}', $text);

        return trim($text);
    }

    /**
     * @return bool
     */
    public static function isCommented(string $text): bool
    {
        $text = str_replace('<!-- Paramètre obligatoire -->', '', $text);

        //ou preg_match('#<\!--(?!-->).*-->#s', '', $text); // plus lourd mais précis
        return preg_match('#<!--[^>]*-->#', $text) > 0;
    }

    /**
     * Remove '<!--', '-->', and everything between.
     * To avoid leaving blank lines, when a comment is both preceded
     * and followed by a newline (ignoring spaces), trim leading and
     * trailing spaces and one of the newlines.
     * (c) WikiMedia /includes/parser/Sanitizer.php.
     *
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

        return (string) $text;
    }

    /**
     * Strip the final point (".") as in <ref> ending.
     *
     *
     * @return string
     */
    public static function stripFinalPoint(string $str): string
    {
        if (str_ends_with($str, '.')) {
            return substr($str, 0, strlen($str) - 1);
        }

        return $str;
    }

    /**
     * Normalize URL for inclusion as a wiki-template value.
     * https://en.wikipedia.org/wiki/Template:Citation_Style_documentation/url
     *
     *
     * @return string
     */
    public static function normalizeUrlForTemplate(string $url): string
    {
        $searchReplace = [
            ' ' => '%20',
            '"' => '%22',
            "'''" => '%27%27%27',
            "''" => '%27%27',
            '<' => '%3c',
            '>' => '%3e',
            '[' => '%5b',
            ']' => '%5d',
            '{{' => '%7b%7b',
            '|' => '%7c',
            '}}' => '%7d%7d',
        ];

        return str_replace(array_keys($searchReplace), array_values($searchReplace), $url);
    }

    /**
     * Add reference separator {{,}} between reference tags. Not-cosmetic changes.
     * Example :
     * "<ref>A</ref><ref>B</ref>" => "<ref>A</ref>{{,}}<ref>B</ref>".
     * "<ref name="A" /> <ref>…" => "<ref name="A" />{{,}}<ref>…".
     * "{{Sfn|...}}<ref name=B>..." => "{{Sfn|...}}{{,}}<ref name=B>...".
     * Skip replacement if {Références | références=...} or {Références nombreuses} or {Références discussion}
     * TODO : {{note}}
     */
    public static function fixConcatenatedRefsSyntax(string $wikiText): string
    {
        // Skip on the rare {Références nombreuses} et {Références discussion} and param "références=..."
        if (preg_match(
                '#\{\{ ?(Références nombreuses|Références discussion)[\s\r\n\t]*\|[^}]*(références|refs)[\s\r\n\t]*=#i',
                $wikiText
            ) > 0) {
            return $wikiText;
        }

        // old style <references><ref name=…>... </references>
        if (preg_match('#<references>[\s\r\n\t]*<ref name=#i', $wikiText) > 0) {
            return $wikiText;
        }


        // Skip if {{Références | références= ... }}
        if (preg_match(
                '#\{\{ ?Références[\s\r\n\t]*\|[^\}]*(références|refs)[\s\r\n\t]*=#i',
                $wikiText
            ) > 0) {
            return $wikiText;
        }

        // carriage return only fund between refs inside {{Références | références= ... }}
        // if carriage return </ref>\n<ref… outside that template, the ref-link appears on a new line => \n deleted
        $wikiText = preg_replace('#</ref>[\n\r\s]*<ref#', '</ref>{{,}}<ref', $wikiText);
        $wikiText = preg_replace('#(<ref name=[^\/\>\r\n]+/>)[\n\r\s]*<ref#', "$1" . '{{,}}<ref', $wikiText);

        // {{Sfn|...}}{{Sfn|...}}
        $wikiText = preg_replace('#(\{\{sfn[\s\|\n\r][^\{\}]+}})\s*(\{\{sfn[\s\|\n\r])#i', '$1{{,}}$2', $wikiText);
        // </ref>{{Sfn|...}} => </ref>{{,}}{{Sfn|...}}
        $wikiText = preg_replace('#</ref>\s*(\{\{sfn[\s\|\n\r])#i', '</ref>{{,}}$1', $wikiText);
        // <ref name="A" />{{Sfn|...}} => <ref name="A" />{{,}}{{Sfn|...}}
        $wikiText = preg_replace('#(<ref name=[^\/\>]+/>)\s*(\{\{sfn[\s\|\n\r])#i', "$1{{,}}$2", $wikiText);
        // {{Sfn|...}}<ref… => {{Sfn|...}}{{,}}<ref…
        $wikiText = preg_replace('#(\{\{sfn[\s\|\n\r][^\{\}]+}})\s*<ref#i', '$1{{,}}<ref', $wikiText);

        return $wikiText;
    }

    /**
     * Fix some generic wiki syntax. Not-cosmetic changes.
     * todo : final . in ref
     * todo punctuation before ref ".<ref…" (mais exclure abbréviations "etc.<ref")
     */
    public static function fixRefSpacingSyntax(string $text): string
    {
        // space before ref. (also <ref name=A/>) todo : exlure 1234<ref>... qui complique lecture ?

        // spaces before ref, not preceded by "|" (cosmetic in wiki-tables) or 0-9 number (reading confusion)
        // Regex : negative-lookbehind (?<!fubar) for not preceded by fubar
        $text = preg_replace('#(?<![\|\d])\s+<ref>#', '<ref>', $text); // not cosmetic
        $text = preg_replace('#(?<![\|\d])\s+(<ref name=[^>]+>)#', '$1', $text); // not cosmetic

        // space+punctuation after ref
        $text = preg_replace('#</ref>\s+\.#', '</ref>.', $text); // not cosmetic

        return preg_replace('#</ref>\s+\,#', '</ref>,', $text);
    }

    /**
     * TODO move
     */
    public static function fixGenericWikiSyntax(string $text): string
    {
        $text = self::fixConcatenatedRefsSyntax($text);
        return self::fixRefSpacingSyntax($text);
    }
}
