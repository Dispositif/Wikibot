<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe/Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Utils;

class WikiRefsFixer
{
    public static function fixRefWikiSyntax(string $text): string
    {
        // Skip syntax fixes on article's bottom with special refs list.
        $initialWorkText = $workText = self::beforeSpecialRefsList($text);

        $workText = self::fixConcatenatedRefsSyntax($workText);
        $workText = self::fixRefSpacingSyntax($workText);

        return str_replace($initialWorkText, $workText, $text);
    }

    protected static function beforeSpecialRefsList(string $text): string
    {
        // regex option /s for dot matches carriage return
        if (preg_match('#(.*)\{\{ ?(?:Références|Références nombreuses|Références discussion)[\s\r\n\t]*\|[^\{\}]*(références|refs)[\s\r\n\t]*=#si', $text, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('#(.*)<references>.*<ref name=#si', $text, $matches)) {
            return trim($matches[1]);
        }

        return $text;
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
        if (self::hasSpecialRefsList($wikiText)) {
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

    private static function hasSpecialRefsList(string $wikiText): bool
    {
        // Skip on the rare {Références nombreuses} et {Références discussion} and param "références=..."
        if (preg_match(
                '#\{\{ ?(Références nombreuses|Références discussion)[\s\r\n\t]*\|[^}]*(références|refs)[\s\r\n\t]*=#i',
                $wikiText
            ) > 0) {
            return true;
        }

        // old style <references><ref name=…>... </references>
        if (preg_match('#<references>[\s\r\n\t]*<ref name=#i', $wikiText) > 0) {
            return true;
        }


        // Skip if {{Références | références= ... }}
        if (preg_match(
                '#\{\{ ?Références[\s\r\n\t]*\|[^\}]*(références|refs)[\s\r\n\t]*=#i',
                $wikiText
            ) > 0) {
            return true;
        }

        return false;
    }

    /**
     * Fix some generic wiki syntax. Not-cosmetic changes.
     * todo : final . in ref
     * todo punctuation before ref ".<ref…" (mais exclure abbréviations "etc.<ref")
     */
    public static function fixRefSpacingSyntax(string $text): string
    {
        if (self::hasSpecialRefsList($text)) {
            return $text;
        }
        // space before ref. (also <ref name=A/>) todo : exlure 1234<ref>... qui complique lecture ?

        // spaces before ref, not preceded by "|", "=" (cosmetic in wiki-tables) or 0-9 number (reading confusion)
        // Regex : negative-lookbehind (?<!fubar) for not preceded by fubar
        $text = preg_replace('#(?<![\|\d=])\s+<ref>#', '<ref>', $text); // not cosmetic
        $text = preg_replace('#(?<![\|\d=])\s+(<ref name=[^>]+>)#', '$1', $text); // not cosmetic

        // space+punctuation after ref
        $text = preg_replace('#</ref>\s+\.#', '</ref>.', $text); // not cosmetic

        return preg_replace('#</ref>\s+\,#', '</ref>,', $text);
    }
}
