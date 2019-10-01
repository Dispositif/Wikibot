<?php


namespace App\Domain;

/**
 * Class PredictFromTypo
 */
class PredictFromTypo
{

    private $firstnameList = [];

    // todo inject listManager  (constru or setter)
    public function __construct(array $firstnameList = null)
    {
        $this->firstnameList = ['Paul', 'Pierre', 'Jean-Pierre']; // temp
        if (!is_null($firstnameList)) {
            $this->firstnameList = $firstnameList;
        }
    }

    /**
     * Determine name and firstname from a string where both are mixed or abbreviated
     * Prediction from typo pattern, statistical analysis and list of famous firstnames
     *
     * @param string $author
     *
     * @return array
     */
    public function predictNameFirstName(string $author): array
    {
        // multiple authors // todo? explode authors
        if ($this->hasManyAuthors($author)) {
            return ['fail' => '2+ authors in string'];
        }

        // ALLUPPER, FIRSTUPPER, ALLLOWER, MIXED, INITIAL, ALLNUMBER, WITHNUMBER, DASHNUMBER, URL, ITALIC, BIBABREV,
        // AND, VIRGULE, PUNCTUATION
        $typoPattern = $this->typoPatternFromAuthor($author);
        $tokenAuthor = preg_split('#[ ]+#', $author);

        // Paul Durand
        if ($typoPattern === 'FIRSTUPPER FIRSTUPPER'
            && $this->checkFirstname($tokenAuthor[0])
            && !empty($tokenAuthor[1])
        ) {
            return ['firstname' => $tokenAuthor[0], 'name' => $tokenAuthor[1],];
        }

        // Zorglub Durand => singe savant
        if ($typoPattern === 'FIRSTUPPER FIRSTUPPER' && $this->checkFirstname($tokenAuthor[0])
            && !empty($tokenAuthor[1])
        ) {
            return ['fail' => 'firstname not in list'];
        }

        // Jean-Pierre Durand
        if ($typoPattern === 'MIXED FIRSTUPPER' && !empty($tokenAuthor[0]) && !empty($tokenAuthor[1])
            && $this->checkFirstname($tokenAuthor[0])
        ) {
            return ['firstname' => $tokenAuthor[0], 'name' => $tokenAuthor[1]];
        }

        // A. Durand
        if ($typoPattern === 'INITIAL FIRSTUPPER' && !empty($tokenAuthor[0]) && !empty($tokenAuthor[1])) {
            // todo : prendre name après dernier point et rétablir "A. B." == FIRSTCAP
            return ['firstname' => $tokenAuthor[0], 'name' => $tokenAuthor[1]];
        }
        // A. B. Durand (todo : gérer "A.B. Durand")
        if ($typoPattern === 'INITIAL INITIAL FIRSTUPPER' && !empty($tokenAuthor[0]) && !empty($tokenAuthor[2])) {
            return ['firstname' => $tokenAuthor[0].' '.$tokenAuthor[1], 'name' => $tokenAuthor[2]];
        }
        // Durand, P.
        if ($typoPattern === 'FIRSTUPPER VIRGULE INITIAL' && !empty($tokenAuthor[0]) && !empty($tokenAuthor[1])) {
            $name = trim(str_replace(',', '', $tokenAuthor[0]));

            return ['firstname' => $tokenAuthor[1], 'name' => $name];
        }

        return [
            'fail' => 'unknown typo pattern',
            'pattern' => $typoPattern,
        ];
    }

    /**
     * From underTwoAuthors() by MartinS
     * Return true if 0 or 1 author in $author; false otherwise
     *
     * @param $author
     *
     * @return bool
     */
    private function hasManyAuthors($author): bool
    {
        $chars = count_chars(trim($author));
        if ($chars[ord(";")] > 0 || $chars[ord(" ")] > 2 || $chars[ord(",")] > 1) {
            return true;
        }

        return false;
    }

    /**
     * todo legacy : refac with array+trim
     * Tokenize pour analyse patterns
     * importance de la ponctuation (voir tableaux recherche)
     * Inspiré des travaux CLÉO : http://bilbo.hypotheses.org/193 et http://bilbo.hypotheses.org/133 /111
     * ALLUPPER, FIRSTUPPER, ALLLOWER, MIXED, INITIAL, ALLNUMBER, WITHNUMBER, DASHNUMBER, URL, ITALIC, BIBABREV, AND, VIRGULE, PUNCTUATION,
     * dewikif > URL > BIBABREV > VIRGULE / POINT / ITALIQUE / GUILLEMET > PUNCTUATION > split? > ...
     * BIBABREV = dir. trad. Jr.
     * Current version 2 : Tokenize all the space " ". Initials first funds. VIRGULE, PUNCTUATION
     *
     * @param $text
     *
     * @return string
     */
    public function typoPatternFromAuthor(string $text): string
    {
        // todo : unWikify (pour biblio faudra garder italique)

        // URL = adresse web
        $text = preg_replace('#\bhttps?\:\/\/[^ ]+#i', ' URL ', $text);
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
            '#\b[\(\[]?(dir|trad)\.[\)\]]?#i',
            ' BIBABREV ',
            $text
        ); // TODO : améliorer regex

        // PUNCTUATION : sans virgule, sans &, sans point, sans tiret petit '-'
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
        foreach ($tokens AS $tok) {
            // virer tok vide > NUMBER > INITIAL > lower/upper

            if (empty($tok)) {
                continue;
            }

            if (preg_match('#^(INITIAL|URL|AND|VIRGULE|BIBABREV|PUNCTUATION)$#', $tok) > 0) {
                $res .= ' '.$tok;
                // "J. R . R." => INITIAL (1 seule fois)
                //                $res = str_replace(
                //                    'INITIAL INITIAL',
                //                    'INITIAL',
                //                    $res
                //                );
            }elseif (preg_match('#^[0-9]+$#', $tok) > 0) {
                $res .= ' ALLNUMBER';
            }elseif (preg_match('#^[0-9\-]+$#', $tok) > 0) {
                $res .= ' DASHNUMBER';
            }elseif (preg_match('#[0-9]#', $tok) > 0) {
                $res .= ' WITHNUMBER';
            }elseif (mb_strtolower($tok, 'UTF-8') === $tok) {
                $res .= ' ALLLOWER';
            }elseif (mb_strtoupper($tok, 'UTF-8') === $tok) {
                $res .= ' ALLUPPER';
            }elseif (mb_strtoupper(substr($tok, 0, 1), 'UTF-8') === substr(
                    $tok,
                    0,
                    1
                ) AND mb_strtolower(substr($tok, 1), 'UTF-8') === substr($tok, 1)
            ) {
                $res .= ' FIRSTUPPER';
            }elseif (preg_match('#[a-zA-Zàéù]#', $tok) > 0) {
                $res .= ' MIXED';
            }else {
                $res .= ' UNKNOW';
            }
        }

        return trim($res); // todo return array ?
    }

    /**
     * Check if $firstname in the list of firstnames
     *
     * @param $firstname
     *
     * @return bool
     */
    private function checkFirstname($firstname): bool
    {
        // todo? sanitize firstname  (ucfirst?)
        if (strlen(trim($firstname)) >= 2 && in_array($firstname, $this->firstnameList)) {
            return true;
        }
        // todo : add $firstname to list of unknow firstnames
        //        file_put_contents('./temp/LISTE_prenominconnu_nom.txt', utf8_encode($firstname), FILE_APPEND | LOCK_EX);
        return false;
    }
}
