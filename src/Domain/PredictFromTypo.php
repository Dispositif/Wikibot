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
 * Class PredictFromTypo.
 */
class PredictFromTypo
{
    /**
     * @var CorpusInterface|null
     */
    private $corpusAdapter;

    private $tokenValue = [];

    private $unknownCorpusName = 'corpus_unknow_firstname'; // temp refac

    private $firstnameCorpusName = 'firstname'; // temp refac

    public function __construct(?CorpusInterface $corpus = null)
    {
        $this->corpusAdapter = $corpus;
    }

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
                '#\b[(\[]?(dir|trad)\.[)\]]?#i' => function ($match) {
                    $this->tokenValue['BIBABREV'][] = $match[0]; // [1] = dir

                    return ' BIBABREV ';
                },
                // AND
                '# (et|and|$|with|avec) #' => function ($match) {
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

    /**
     * From underTwoAuthors() by MartinS@Wikipedia
     * Return true if 0 or 1 author in $author; false otherwise.
     *
     * @param $author
     *
     * @return bool
     */
    public static function hasManyAuthors(string $author): bool
    {
        $author = WikiTextUtil::unWikify($author);
        $chars = count_chars(trim($author));
        // todo : "et" + "and" ?
        if ($chars[ord('&')] > 0 || $chars[ord(';')] > 0 || $chars[ord(' ')] >= 3 || $chars[ord(',')] > 1) {
            return true;
        }

        return false;
    }

    /**
     * Check if the name is already inside the corpus of firstnames.
     *
     * @param string $firstname
     * @param bool   $logInCorpus
     *
     * @return bool
     */
    private function checkFirstname(string $firstname, bool $logInCorpus = false): bool
    {
        if (!$this->corpusAdapter) {
            return false;
        }

        $sanitizedName = mb_strtolower($firstname);
        if (strlen(trim($firstname)) >= 2
            && $this->corpusAdapter->inCorpus($sanitizedName, $this->firstnameCorpusName)
        ) {
            return true;
        }

        // add the name to a corpus
        if ($this->corpusAdapter && $logInCorpus) {
            $this->corpusAdapter->addNewElementToCorpus($this->unknownCorpusName, $sanitizedName);
        }

        return false;
    }

    /**
     * todo Legacy.
     * Determine name and firstname from a string where both are mixed or abbreviated
     * Prediction from typo pattern, statistical analysis and list of famous firstnames.
     *
     * @param string $author
     *
     * @return array
     */
    public function predictNameFirstName(string $author): array
    {
        // multiple authors // todo? explode authors
        if (self::hasManyAuthors($author)) {
            return ['fail' => '2+ authors in string'];
        }

        // ALLUPPER, FIRSTUPPER, ALLLOWER, MIXED, INITIAL, ALLNUMBER, WITHNUMBER, DASHNUMBER, URL, ITALIC, BIBABREV,
        // AND, COMMA, PUNCTUATION
        $typoPattern = $this->typoPatternFromAuthor($author)['pattern'];
        $tokenAuthor = preg_split('#[ ]+#', $author);

        if ('FIRSTUPPER FIRSTUPPER' === $typoPattern && !empty($tokenAuthor[1])) {
            // Paul Durand
            if ($this->checkFirstname($tokenAuthor[0], true) && !$this->checkFirstname($tokenAuthor[1])) {
                return ['firstname' => $tokenAuthor[0], 'name' => $tokenAuthor[1]];
            }
            // Durand Paul
            if ($this->checkFirstname($tokenAuthor[1]) && !$this->checkFirstname($tokenAuthor[0])) {
                return ['firstname' => $tokenAuthor[1], 'name' => $tokenAuthor[0]];
            }

            // Pierre Paul
            if ($this->checkFirstname($tokenAuthor[1]) && $this->checkFirstname($tokenAuthor[0])) {
                return ['fail' => 'both names in the firstnames corpus'];
            }

            return ['fail' => 'firstname not in corpus'];
        }

        // Jean-Pierre Durand
        if ('MIXED FIRSTUPPER' === $typoPattern && !empty($tokenAuthor[0]) && !empty($tokenAuthor[1])) {
            // Jean-Pierre Durand
            if ($this->checkFirstname($tokenAuthor[0], true) && !$this->checkFirstname($tokenAuthor[1])) {
                return ['firstname' => $tokenAuthor[0], 'name' => $tokenAuthor[1]];
            }
            // Ducroz-Lacroix Pierre
            if ($this->checkFirstname($tokenAuthor[1]) && !$this->checkFirstname($tokenAuthor[0])) {
                return ['firstname' => $tokenAuthor[1], 'name' => $tokenAuthor[0]];
            }
            // Luc-Zorglub Durand
            $pos = mb_strpos($tokenAuthor[0], '-');
            $firstnamePart = mb_substr($tokenAuthor[0], 0, $pos);
            if ($pos > 0 && $this->checkFirstname($firstnamePart)) {
                return ['firstname' => $tokenAuthor[0], 'name' => $tokenAuthor[1]];
            }

            return ['fail' => 'firstname MIXED not in corpus'];
        }

        // A. Durand
        if ('INITIAL FIRSTUPPER' === $typoPattern && !empty($tokenAuthor[0]) && !empty($tokenAuthor[1])) {
            // get last "." position (compatible with "A. B. Durand")
            $pos = mb_strrpos($author, '.');

            return [
                'firstname' => substr($author, 0, $pos + 1),
                'name' => trim(substr($author, $pos + 1)),
            ];
        }

        // Durand, P.
        if ('FIRSTUPPER COMMA INITIAL' === $typoPattern && !empty($tokenAuthor[0]) && !empty($tokenAuthor[1])) {
            $name = trim(str_replace(',', '', $tokenAuthor[0]));

            return ['firstname' => $tokenAuthor[1], 'name' => $name];
        }

        return [
            'fail' => 'unknown typo pattern',
            'pattern' => $typoPattern,
        ];
    }

}
