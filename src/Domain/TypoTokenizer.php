<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Utils\TextUtil;
use App\Domain\Utils\WikiTextUtil;

/**
 * Tokenizing string in predefined typographic categories.
 * Used for typographic pattern analysis & recognition.
 * Class TypoTokenizer.
 */
class TypoTokenizer
{
    private $tokenValue = [];

    /**
     * Tokenize into typographic pattern.
     * See studies from CLÉO : http://bilbo.hypotheses.org/193 et http://bilbo.hypotheses.org/133 /111
     * ALLUPPER, FIRSTUPPER, ALLLOWER, MIXED, INITIAL, ALLNUMBER, WITHNUMBER, DASHNUMBER, URL, ITALIC, BIBABREV, AND,
     * COMMA, PUNCTUATION,
     * Example of the returned array :
     * string => 'Penaud, Jean-Pierre'
     * pattern => 'FIRSTUPPER COMMA MIXED'
     * tokens => [ 0 => 'Penaud', 1 => ',', 2 => 'Jean-Pierre']
     *
     * @param string $text
     *
     * @return array (see example)
     */
    public function typoPatternFromAuthor(string $text): array
    {
        $res['string'] = $text;
        $modText = TextUtil::replaceNonBreakingSpaces($text);

        // unWikify or not ? remove wikilinks and bold/italic wikicode
        $modText = WikiTextUtil::unWikify($modText);

        /**
         * Pre-process : add spaces between relevant typographic items
         */
        $this->tokenValue = [];
        $modText = $this->preprocessTypoPattern($modText);


        // PUNCTUATION conversion
        $punctuationColl = array_filter(
            TextUtil::ALL_PUNCTUATION,
            function ($value) {
                // skip punctuation chars from mixed names (example : "Pierre-Marie L'Anglois")
                return (!in_array($value, ["'", '-', '-']));
            }
        );
        // don't use str_split() which cuts on 1 byte length (≠ multibytes chars)
        $modText = str_replace($punctuationColl, ' PUNCTUATION ', $modText);

        // Split the string
        $tokens = preg_split('#[ ]+#', $modText);
        $res['pattern'] = '';
        foreach ($tokens as $tok) {
            if (empty($tok)) {
                continue;
            }
            if (preg_match('#^(INITIAL|URL|AND|COMMA|BIBABREV|PUNCTUATION)$#', $tok, $matches) > 0) {
                $res['pattern'] .= ' '.$tok;
                if (in_array($matches[1], ['COMMA', 'PUNCTUATION']) || empty($matches[1])) {
                    $res['value'][] = '*';
                } else {
                    $res['value'][] = current($this->tokenValue[$matches[1]]);
                    next($this->tokenValue[$matches[1]]);
                }
                //"J. R . R." => INITIAL (1 seule fois)
                // $res = str_replace('INITIAL INITIAL', 'INITIAL', $res);
            } elseif (preg_match('#^[0-9]+$#', $tok) > 0) {
                $res['pattern'] .= ' ALLNUMBER';
                $res['value'][] = $tok;
            } elseif (preg_match('#^[0-9\-]+$#', $tok) > 0) {
                $res['pattern'] .= ' DASHNUMBER';
                $res['value'][] = $tok;
            } elseif (preg_match('#[0-9]#', $tok) > 0) {
                $res['pattern'] .= ' WITHNUMBER';
                $res['value'][] = $tok;
            } elseif (mb_strtolower($tok, 'UTF-8') === $tok) {
                $res['pattern'] .= ' ALLLOWER';
                $res['value'][] = $tok;
            } elseif (mb_strtoupper($tok, 'UTF-8') === $tok) {
                $res['pattern'] .= ' ALLUPPER';
                $res['value'][] = $tok;
            } elseif (mb_strtoupper(substr($tok, 0, 1), 'UTF-8') === substr($tok, 0, 1)
                && mb_strtolower(substr($tok, 1), 'UTF-8') === substr($tok, 1)
            ) {
                $res['pattern'] .= ' FIRSTUPPER';
                $res['value'][] = $tok;
            } elseif (preg_match('#[a-zA-Zàéù]#', $tok) > 0) {
                $res['pattern'] .= ' MIXED';
                $res['value'][] = $tok;
            } else {
                $res['pattern'] .= ' UNKNOW';
                $res['value'][] = $tok;
            }
        }

        $res['pattern'] = trim($res['pattern']);

        return $res;
    }

    /**
     * Pre-process text : add spaces between relevant typographic items.
     * Save values by types in $tokenValue.
     *
     * @param string $modText
     *
     * @return string
     */
    private function preprocessTypoPattern(string $modText): string
    {
        $modText = preg_replace_callback_array(
            [
                // URL
                '#\bhttps?://[^ \]]+#i' => function ($match) {
                    // '#https?\:\/\/[\w\-_]+(\.[\w\-_]+)+([\w\-\.,@?^=%&amp;:/~\+\#]*[\w\-\@?^=%&amp;/~\+#])?#'
                    $this->tokenValue['URL'][] = $match[0];

                    return ' URL ';
                },
                // BIBABREV : "dir.", "trad.", "(dir.)", "[dir.]", etc.
                '#\b[(\[]?(collectif|coll\.|dir\.|trad\.|coord\.|ill\.)[)\]]?#i' => function ($match) {
                    $this->tokenValue['BIBABREV'][] = $match[0]; // [1] = dir

                    return ' BIBABREV ';
                },
                // collectif
                // AND
                '# (et|and|&|with|avec) #' => function ($match) {
                    $this->tokenValue['AND'][] = $match[1];

                    return ' AND ';
                },
                // COMMA
                '#,#' => function ($match) {
                    return ' COMMA ';
                },
                // INITIAL : 2) convert letter ("A.") or junior ("Jr.") or senior ("Sr.")
                // extract initial before "." converted in PUNCTUATION
                // Note : \b word boundary match between "L" and "'Amour" in "L'Amour"  (for [A-Z]\b)
                // \b([A-Z]\. |[A-Z] |JR|Jr\.|Jr\b|Sr\.|Sr\b)+ for grouping "A. B." in same INITIAL ?
                "#\b([A-Z]\.|[A-Z] |JR|Jr\.|Jr\b|Sr\.|Sr\b)#" => function ($match) {
                    $this->tokenValue['INITIAL'][] = $match[0];

                    return ' INITIAL ';
                },
            ],
            $modText,
            40
        );

        return $modText;
    }

}
