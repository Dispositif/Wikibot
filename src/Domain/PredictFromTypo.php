<?php

declare(strict_types=1);

namespace App\Domain;

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

    private $unknownCorpusName = 'corpus_unknow_firstname'; // temp refac

    private $firstnameCorpusName = 'firstname'; // temp refac

    public function __construct(?CorpusInterface $corpus = null)
    {
        $this->corpusAdapter = $corpus;
    }

    /**
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
        // AND, VIRGULE, PUNCTUATION
        $typoPattern = $this->typoPatternFromAuthor($author);
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
        if ('FIRSTUPPER VIRGULE INITIAL' === $typoPattern && !empty($tokenAuthor[0]) && !empty($tokenAuthor[1])) {
            $name = trim(str_replace(',', '', $tokenAuthor[0]));

            return ['firstname' => $tokenAuthor[1], 'name' => $name];
        }

        return [
            'fail' => 'unknown typo pattern',
            'pattern' => $typoPattern,
        ];
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
     * todo legacy : refac with array+trim
     * Tokenize for typographic analysis
     * See studies by CLÉO : http://bilbo.hypotheses.org/193 et http://bilbo.hypotheses.org/133 /111
     * ALLUPPER, FIRSTUPPER, ALLLOWER, MIXED, INITIAL, ALLNUMBER, WITHNUMBER, DASHNUMBER, URL, ITALIC, BIBABREV, AND,
     * VIRGULE, PUNCTUATION,
     * Process order : unWikify > URL > BIBABREV > VIRGULE / POINT / ITALIQUE / GUILLEMET > PUNCTUATION > split? > ...
     * BIBABREV = "dir.", "trad." ("Jr." = INITIAL)
     * Current version 2 : Tokenize all the space " ". Initials first funds. VIRGULE, PUNCTUATION.
     *
     * @param $text
     *
     * @return string
     */
    public function typoPatternFromAuthor(string $text): string
    {
        // todo : unWikify? (pour ref biblio faudrait garder italique)

        // URL = adresse web
        $text = preg_replace('#\bhttps?://[^ ]+#i', ' URL ', $text);
        //$text = preg_replace( '#https?\:\/\/[\w\-_]+(\.[\w\-_]+)+([\w\-\.,@?^=%&amp;:/~\+\#]*[\w\-\@?^=%&amp;/~\+#])?#', ' URL ', $text);

        // AND = and, et, &, with, avec
        $text = str_replace(
            [' et ', ' and ', ' & ', ' with ', ' avec '],
            ' AND ',
            $text
        );

        // VIRGULE = ,
        $text = str_replace(',', ' VIRGULE ', $text);

        // INITIAL +Junior +Senior // problème "L'Ardoise"
        $text = str_replace(
            "'",
            '',
            $text
        ); // strip apostrophe : L'Ardoise => LArdoise
        $text = preg_replace(
            "#\b([A-Z]\.|[A-Z]\b|Jr\.|Jr\b|Sr\.|Sr\b)(?!=')#",
            ' INITIAL ',
            $text
        );

        // BIBABREV = abréviations bibliographiques : dir. trad. aussi [dir] (dir.)
        $text = preg_replace(
            '#\b[(\[]?(dir|trad)\.[)\]]?#i',
            ' BIBABREV ',
            $text
        ); // TODO : compléter regex

        // PUNCTUATION : sans virgule, sans &, sans point, sans tiret petit '-'
        // don't use str_split() which cuts on 1 byte length (≠ multibytes chars)
        $text = str_replace(
            [
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
            ],
            ' PUNCTUATION ',
            $text
        );
        $tokens = preg_split('#[ ]#', $text);

        $res = '';
        foreach ($tokens as $tok) {
            if (empty($tok)) {
                continue;
            }

            if (preg_match('#^(INITIAL|URL|AND|VIRGULE|BIBABREV|PUNCTUATION)$#', $tok) > 0) {
                $res .= ' '.$tok;
                //"J. R . R." => INITIAL (1 seule fois)
                $res = str_replace('INITIAL INITIAL', 'INITIAL', $res);
            } elseif (preg_match('#^[0-9]+$#', $tok) > 0) {
                $res .= ' ALLNUMBER';
            } elseif (preg_match('#^[0-9\-]+$#', $tok) > 0) {
                $res .= ' DASHNUMBER';
            } elseif (preg_match('#[0-9]#', $tok) > 0) {
                $res .= ' WITHNUMBER';
            } elseif (mb_strtolower($tok, 'UTF-8') === $tok) {
                $res .= ' ALLLOWER';
            } elseif (mb_strtoupper($tok, 'UTF-8') === $tok) {
                $res .= ' ALLUPPER';
            } elseif (mb_strtoupper(substr($tok, 0, 1), 'UTF-8') === substr(
                    $tok,
                    0,
                    1
                ) and mb_strtolower(substr($tok, 1), 'UTF-8') === substr($tok, 1)
            ) {
                $res .= ' FIRSTUPPER';
            } elseif (preg_match('#[a-zA-Zàéù]#', $tok) > 0) {
                $res .= ' MIXED';
            } else {
                $res .= ' UNKNOW';
            }
        }

        return trim($res);
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
}
