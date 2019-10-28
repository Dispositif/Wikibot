<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);


namespace App\Domain;


class PredictAuteur
{
    private $typoPredict;

    /**
     * PredictAuteur constructor.
     */
    public function __construct()
    {
        $this->typoPredict = new PredictFromTypo();
    }

    /**
     * Explode authors from string based on typo pattern recognition
     * See analysis_pattern_auteurs
     *
     * @param string $string
     *
     * @return array|null
     */
    public function predictAuthorNames(string $string): ?array
    {
        $pattern = $this->typoPredict->typoPatternFromAuthor($string);
        $val = $pattern['value'];

        switch ($pattern['pattern']) {
            case 'FIRSTUPPER':
            case 'ALLUPPER':
                return [0 => $val[0]];

            case 'FIRSTUPPER FIRSTUPPER': // Laurent Croizier
            case 'MIXED FIRSTUPPER': // Jean-Paul Marchand
            case 'INITIAL FIRSTUPPER': // Christian Le Boutellier
            case 'FIRSTUPPER MIXED':
            case 'FIRSTUPPER FIRSTUPPER BIBABREV':
            case 'FIRSTUPPER FIRSTUPPER PUNCTUATION BIBABREV': // todo
                return [0 => $val[0].' '.$val[1]];

            case 'FIRSTUPPER FIRSTUPPER FIRSTUPPER':
            case 'FIRSTUPPER INITIAL FIRSTUPPER':
            case 'INITIAL INITIAL FIRSTUPPER':
            case 'FIRSTUPPER ALLLOWER FIRSTUPPER':
                return [0 => $val[0].' '.$val[1].' '.$val[2]];

            case 'FIRSTUPPER FIRSTUPPER FIRSTUPPER FIRSTUPPER':
                // [[Jean Julien Michel Savary]]
            case 'FIRSTUPPER FIRSTUPPER ALLLOWER FIRSTUPPER': // Abbé Guillotin de Corson
            case 'FIRSTUPPER INITIAL INITIAL FIRSTUPPER':
                return [0 => $val[0].' '.$val[1].' '.$val[2].' '.$val[3]];

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
                return [0 => $val[0].' '.$val[1].' '.$val[2].' '.$val[3].' '.$val[4]];

            /**
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
                return [
                    0 => $val[0].' '.$val[1],
                    1 => $val[3].' '.$val[4],
                ];

            // COUPLE
            case 'FIRSTUPPER AND FIRSTUPPER FIRSTUPPER':
                // Renée & Michel Paquet
                return [
                    0 => $val[0].' '.$val[3],
                    1 => $val[2].' '.$val[3],
                ];

            case 'FIRSTUPPER FIRSTUPPER FIRSTUPPER COMMA FIRSTUPPER FIRSTUPPER':
            case 'FIRSTUPPER FIRSTUPPER FIRSTUPPER AND FIRSTUPPER FIRSTUPPER':
                // Didier Du Castel, Claude Estebe
            case 'FIRSTUPPER INITIAL FIRSTUPPER COMMA FIRSTUPPER FIRSTUPPER':
                return [
                    0 => $val[0].' '.$val[1].' '.$val[2],
                    1 => $val[4].' '.$val[5],
                ];

            case 'FIRSTUPPER FIRSTUPPER COMMA FIRSTUPPER FIRSTUPPER FIRSTUPPER':
                // Armin Vit, Bryony Gomez Palacio
            case 'FIRSTUPPER FIRSTUPPER COMMA FIRSTUPPER INITIAL FIRSTUPPER':
            case 'FIRSTUPPER FIRSTUPPER AND FIRSTUPPER FIRSTUPPER FIRSTUPPER':
            case 'FIRSTUPPER FIRSTUPPER COMMA FIRSTUPPER ALLLOWER MIXED':
            case 'FIRSTUPPER FIRSTUPPER AND FIRSTUPPER INITIAL FIRSTUPPER':
                // François Clément, Viton de Saint-Allais
                return [
                    0 => $val[0].' '.$val[1],
                    1 => $val[3].' '.$val[4].' '.$val[5],
                ];

            case 'FIRSTUPPER INITIAL FIRSTUPPER COMMA FIRSTUPPER INITIAL FIRSTUPPER':
            case 'INITIAL INITIAL FIRSTUPPER COMMA INITIAL INITIAL FIRSTUPPER':
                // Eugene P. Kiver, David V. Harris
            case 'INITIAL FIRSTUPPER FIRSTUPPER COMMA FIRSTUPPER INITIAL FIRSTUPPER':
                // H. Trevor Clifford, Peter D. Bostock
            case 'FIRSTUPPER INITIAL FIRSTUPPER AND FIRSTUPPER INITIAL FIRSTUPPER':
            case 'INITIAL INITIAL FIRSTUPPER AND INITIAL INITIAL FIRSTUPPER':
                return [
                    0 => $val[0].' '.$val[1].' '.$val[2],
                    1 => $val[4].' '.$val[5].' '.$val[6],
                ];

            /**
             *  3 authors
             */

            case 'FIRSTUPPER FIRSTUPPER COMMA FIRSTUPPER FIRSTUPPER COMMA MIXED FIRSTUPPER':
                // Andrzej Suchcitz, Ludwik Maik, Wojciech Rojek
            case 'FIRSTUPPER FIRSTUPPER COMMA FIRSTUPPER FIRSTUPPER COMMA FIRSTUPPER FIRSTUPPER':
            case 'FIRSTUPPER FIRSTUPPER COMMA FIRSTUPPER FIRSTUPPER AND FIRSTUPPER FIRSTUPPER':
                // [[Arnaud Bédat]], Gilles Bouleau et Bernard Nicolas
            case 'FIRSTUPPER FIRSTUPPER COMMA MIXED FIRSTUPPER COMMA FIRSTUPPER FIRSTUPPER':
                return [
                    0 => $val[0].' '.$val[1],
                    1 => $val[3].' '.$val[4],
                    2 => $val[6].' '.$val[7],
                ];

            /**
             * 4 authors
             */

            case 'FIRSTUPPER FIRSTUPPER COMMA MIXED FIRSTUPPER COMMA MIXED FIRSTUPPER COMMA FIRSTUPPER FIRSTUPPER':
            case 'FIRSTUPPER FIRSTUPPER COMMA FIRSTUPPER FIRSTUPPER COMMA FIRSTUPPER FIRSTUPPER COMMA FIRSTUPPER FIRSTUPPER':
                return [
                    0 => $val[0].' '.$val[1],
                    1 => $val[3].' '.$val[4],
                    2 => $val[6].' '.$val[7],
                    3 => $val[9].' '.$val[10],
                ];
        }

        return [];
    }

}

