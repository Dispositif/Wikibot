<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Tests;

use App\Domain\PredictAuthors;
use App\Domain\TypoTokenizer;
use PHPUnit\Framework\TestCase;

class TypoTokenizerTest extends TestCase
{
    /**
     * For TDD.
     *
     * @dataProvider patternProvider
     *
     * @param string $text
     * @param string $pattern
     */
    public function testTokenizeAuthor(string $text, string $pattern)
    {
        $tokenizer = new TypoTokenizer();
        $result = $tokenizer->typoPatternFromAuthor($text);
        $this::assertEquals(
            $pattern,
            $result['pattern']
        );
    }

    public function patternProvider()
    {
        return [
            ['B. Marc dir. et Pierre BERGER', 'INITIAL FIRSTUPPER BIBABREV AND FIRSTUPPER ALLUPPER'],
            ['Renée & Michel Paquet', 'FIRSTUPPER AND FIRSTUPPER FIRSTUPPER'],
            ["Jean-Pierre L'Ardoise", 'MIXED MIXED'],
            ['Penaud, Jean-Pierre', 'FIRSTUPPER COMMA MIXED'],
            ['J. Penaud', 'INITIAL FIRSTUPPER'],
            ['A. B. Penaud', 'INITIAL INITIAL FIRSTUPPER'],
            ['123-234-34323 AC234EF 1234 @', 'DASHNUMBER WITHNUMBER ALLNUMBER PUNCTUATION'],
            ['bla http://google.fr 123','ALLLOWER URL ALLNUMBER'],
                        ['A. B. Penaud', 'INITIAL INITIAL FIRSTUPPER'],
            ['Jean Truc-Machine', 'FIRSTUPPER MIXED'],
            ['Armin Vit, Bryony Gomez Palacio', 'FIRSTUPPER FIRSTUPPER COMMA FIRSTUPPER FIRSTUPPER FIRSTUPPER'],
            ['H. Trevor Clifford, Peter D. Bostock', 'INITIAL FIRSTUPPER FIRSTUPPER COMMA FIRSTUPPER INITIAL FIRSTUPPER']
        ];
    }

    /**
     * @dataProvider provideAuthorNames
     */
    public function testPredictAuthorNames($string, $expected)
    {
        $predic = new PredictAuthors();

        $this::assertSame(
            $expected,
            $predic->predictAuthorNames($string)
        );
    }

    public function provideAuthorNames()
    {
        return [
            ['Marc Durand et Pierre Berger', [0 => 'Marc Durand', 1 => 'Pierre Berger']],
            [
                'Marie-Paul Du Breil de Pontbriand',
                [0 => 'Marie-Paul Du Breil de Pontbriand'],
            ],
            ['Renée et Michel Paquet', [0 => 'Renée Paquet', 1 => 'Michel Paquet']],
            ['Francine Musquère et Jean-Michel Mure', [0 => 'Francine Musquère', 1 => 'Jean-Michel Mure']],
            ['Didier Du Castel, Claude Estebe', [0 => 'Didier Du Castel', 1 => 'Claude Estebe']],
        ];
    }
}