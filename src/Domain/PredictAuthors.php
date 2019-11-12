<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Utils\WikiTextUtil;

/**
 * Prediction around many authors in same string.
 * Class PredictAuthors.
 */
class PredictAuthors
{
    private $typoPredict;

    private $authors = [];

    public function __construct()
    {
        $this->typoPredict = new TypoTokenizer();
    }

    /**
     * Explode authors from string based on typo pattern recognition.
     * See analysis_pattern_auteurs.php for stats and corpus generation.
     *
     * @param string $string
     *
     * @return array|null
     */
    public function predictAuthorNames(string $string): ?array
    {
        $pattern = $this->typoPredict->typoPatternFromAuthor($string);

        $this->patternAndValueToAuthors($pattern);

        return $this->authors;
    }

    private function patternAndValueToAuthors(array $pattern): void
    {
        $val = $pattern['value'];
        switch ($pattern['pattern']) {
            case 'FIRSTUPPER':
            case 'ALLUPPER':
                $this->authors = [0 => $val[0]];

                break;

            case 'FIRSTUPPER FIRSTUPPER': // Laurent Croizier
            case 'MIXED FIRSTUPPER': // Jean-Paul Marchand
            case 'INITIAL FIRSTUPPER': // Christian Le Boutellier
            case 'FIRSTUPPER MIXED':
            case 'FIRSTUPPER FIRSTUPPER BIBABREV':
            case 'FIRSTUPPER FIRSTUPPER PUNCTUATION BIBABREV':
                $this->authors = [0 => $val[0].' '.$val[1]];

                break;

            case 'FIRSTUPPER FIRSTUPPER FIRSTUPPER':
            case 'FIRSTUPPER INITIAL FIRSTUPPER':
            case 'INITIAL INITIAL FIRSTUPPER':
            case 'FIRSTUPPER ALLLOWER FIRSTUPPER':
                $this->authors = [0 => $val[0].' '.$val[1].' '.$val[2]];

                break;

            case 'FIRSTUPPER FIRSTUPPER FIRSTUPPER FIRSTUPPER':
                // [[Jean Julien Michel Savary]]
            case 'FIRSTUPPER FIRSTUPPER ALLLOWER FIRSTUPPER': // Abbé Guillotin de Corson
            case 'FIRSTUPPER INITIAL INITIAL FIRSTUPPER':
                $this->authors = [0 => $val[0].' '.$val[1].' '.$val[2].' '.$val[3]];

                break;

            // NOBLESSE
            case 'FIRSTUPPER FIRSTUPPER FIRSTUPPER ALLLOWER FIRSTUPPER':
                // [[Toussaint Du Breil de Pontbriand]]
            case 'MIXED FIRSTUPPER ALLLOWER FIRSTUPPER FIRSTUPPER':
                // [[Pierre-Suzanne Lucas de La Championnière]]
            case 'FIRSTUPPER ALLLOWER FIRSTUPPER ALLLOWER FIRSTUPPER':
                // [[Toussaint du Breil de Pontbriand]]
            case 'MIXED FIRSTUPPER FIRSTUPPER ALLLOWER FIRSTUPPER':
                // Marie-Paul Du Breil de Pontbriand
            case 'MIXED ALLLOWER FIRSTUPPER ALLLOWER FIRSTUPPER':
                // Marie-Paul du Breil de Pontbriand
            case 'FIRSTUPPER FIRSTUPPER FIRSTUPPER FIRSTUPPER FIRSTUPPER':
                // Mohamed El Aziz Ben Achour
                $this->authors = [0 => $val[0].' '.$val[1].' '.$val[2].' '.$val[3].' '.$val[4]];

                break;

            /*
             *  2 authors
             */

            case 'FIRSTUPPER FIRSTUPPER AND FIRSTUPPER FIRSTUPPER':
                // Robert Sablayrolles et Argitxu Beyrie
            case 'MIXED FIRSTUPPER AND FIRSTUPPER FIRSTUPPER':
            case 'FIRSTUPPER FIRSTUPPER AND MIXED FIRSTUPPER':
                // Francine Musquère et Jean-Michel Mure
            case 'FIRSTUPPER FIRSTUPPER COMMA FIRSTUPPER FIRSTUPPER':
                // Annie Lagueyrie, Philippe Maviel
            case 'FIRSTUPPER FIRSTUPPER COMMA MIXED FIRSTUPPER':
            case 'FIRSTUPPER FIRSTUPPER COMMA FIRSTUPPER MIXED':
            case 'MIXED FIRSTUPPER COMMA FIRSTUPPER FIRSTUPPER':
            case 'INITIAL FIRSTUPPER COMMA INITIAL FIRSTUPPER':
            case 'FIRSTUPPER MIXED AND FIRSTUPPER FIRSTUPPER':
                $this->authors = [
                    0 => $val[0].' '.$val[1],
                    1 => $val[3].' '.$val[4],
                ];

                break;

            // COUPLE
            case 'FIRSTUPPER AND FIRSTUPPER FIRSTUPPER':
                // Renée & Michel Paquet
                $this->authors = [
                    0 => $val[0].' '.$val[3],
                    1 => $val[2].' '.$val[3],
                ];

                break;

            case 'FIRSTUPPER FIRSTUPPER FIRSTUPPER COMMA FIRSTUPPER FIRSTUPPER':
            case 'FIRSTUPPER FIRSTUPPER FIRSTUPPER AND FIRSTUPPER FIRSTUPPER':
                // Didier Du Castel, Claude Estebe
            case 'FIRSTUPPER INITIAL FIRSTUPPER COMMA FIRSTUPPER FIRSTUPPER':
                $this->authors = [
                    0 => $val[0].' '.$val[1].' '.$val[2],
                    1 => $val[4].' '.$val[5],
                ];

                break;

            case 'FIRSTUPPER FIRSTUPPER COMMA FIRSTUPPER FIRSTUPPER FIRSTUPPER':
                // Armin Vit, Bryony Gomez Palacio
            case 'FIRSTUPPER FIRSTUPPER COMMA FIRSTUPPER INITIAL FIRSTUPPER':
            case 'FIRSTUPPER FIRSTUPPER AND FIRSTUPPER FIRSTUPPER FIRSTUPPER':
            case 'FIRSTUPPER FIRSTUPPER COMMA FIRSTUPPER ALLLOWER MIXED':
            case 'FIRSTUPPER FIRSTUPPER AND FIRSTUPPER INITIAL FIRSTUPPER':
                // François Clément, Viton de Saint-Allais
                $this->authors = [
                    0 => $val[0].' '.$val[1],
                    1 => $val[3].' '.$val[4].' '.$val[5],
                ];

                break;

            case 'FIRSTUPPER INITIAL FIRSTUPPER COMMA FIRSTUPPER INITIAL FIRSTUPPER':
            case 'INITIAL INITIAL FIRSTUPPER COMMA INITIAL INITIAL FIRSTUPPER':
                // Eugene P. Kiver, David V. Harris
            case 'INITIAL FIRSTUPPER FIRSTUPPER COMMA FIRSTUPPER INITIAL FIRSTUPPER':
                // H. Trevor Clifford, Peter D. Bostock
            case 'FIRSTUPPER INITIAL FIRSTUPPER AND FIRSTUPPER INITIAL FIRSTUPPER':
            case 'INITIAL INITIAL FIRSTUPPER AND INITIAL INITIAL FIRSTUPPER':
                $this->authors = [
                    0 => $val[0].' '.$val[1].' '.$val[2],
                    1 => $val[4].' '.$val[5].' '.$val[6],
                ];

                break;

            /*
             *  3 authors
             */

            case 'FIRSTUPPER FIRSTUPPER COMMA FIRSTUPPER FIRSTUPPER COMMA MIXED FIRSTUPPER':
                // Andrzej Suchcitz, Ludwik Maik, Wojciech Rojek
            case 'FIRSTUPPER FIRSTUPPER COMMA FIRSTUPPER FIRSTUPPER COMMA FIRSTUPPER FIRSTUPPER':
            case 'FIRSTUPPER FIRSTUPPER COMMA FIRSTUPPER FIRSTUPPER AND FIRSTUPPER FIRSTUPPER':
                // [[Arnaud Bédat]], Gilles Bouleau et Bernard Nicolas
            case 'FIRSTUPPER FIRSTUPPER COMMA MIXED FIRSTUPPER COMMA FIRSTUPPER FIRSTUPPER':
                $this->authors = [
                    0 => $val[0].' '.$val[1],
                    1 => $val[3].' '.$val[4],
                    2 => $val[6].' '.$val[7],
                ];

                break;

            /*
             * 4 authors
             */

            case 'FIRSTUPPER FIRSTUPPER COMMA MIXED FIRSTUPPER COMMA MIXED FIRSTUPPER COMMA FIRSTUPPER FIRSTUPPER':
            case 'FIRSTUPPER FIRSTUPPER COMMA FIRSTUPPER FIRSTUPPER COMMA FIRSTUPPER FIRSTUPPER COMMA FIRSTUPPER FIRSTUPPER':
                $this->authors = [
                    0 => $val[0].' '.$val[1],
                    1 => $val[3].' '.$val[4],
                    2 => $val[6].' '.$val[7],
                    3 => $val[9].' '.$val[10],
                ];

                break;
        }
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
}
