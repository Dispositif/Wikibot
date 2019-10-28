<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Tests;

use App\Domain\PredictAuteur;
use App\Domain\PredictFromTypo;
use App\Infrastructure\CorpusAdapter;
use PHPUnit\Framework\TestCase;

class PredictFromTypoTest extends TestCase
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
        $predict = new PredictFromTypo();
        $tokenize = $predict->typoPatternFromAuthor($text);
        $this::assertEquals(
            $pattern,
            $tokenize['pattern']
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
            //            ['A. B. Penaud', 'INITIAL FIRSTUPPER'],
        ];
    }

    /**
     * @dataProvider provideAuthorNames
     */
    public function testPredictAuthorNames($string, $expected)
    {
        $predic = new PredictAuteur();

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

    /**
     * @dataProvider authorProvider
     *
     * @param $author
     * @param $expected
     */
    public function testPredictNameFirstName($author, $expected)
    {
        $corpus = new CorpusAdapter();
        // lowercap !!!!!
        $corpus->setCorpusInStorage(
            'firstname',
            [
                'totoro',
                'pierre',
                'paul',
                'jean',
            ]
        );

        $predict = new PredictFromTypo($corpus);
        $this::assertEquals(
            $expected,
            $predict->predictNameFirstName($author)
        );
    }

    public function authorProvider()
    {
        return [
            ['Totoro Penaud', ['firstname' => 'Totoro', 'name' => 'Penaud']],
            ['Jean-Pierre Penaud', ['firstname' => 'Jean-Pierre', 'name' => 'Penaud']],
            ['J. Penaud', ['firstname' => 'J.', 'name' => 'Penaud']],
            ['Penaud, J.', ['firstname' => 'J.', 'name' => 'Penaud']],
            ['A. Durand', ['firstname' => 'A.', 'name' => 'Durand']],
            ['A. B. Durand', ['fail' => 'unknown typo pattern', 'pattern' => 'INITIAL INITIAL FIRSTUPPER']],
            ['Pierre Durand, Paul Marchal', ['fail' => '2+ authors in string']],
            ['Babar Elephant', ['fail' => 'firstname not in corpus']],
        ];
    }

    public function testWithStorageCorpus()
    {
        $corpus = new CorpusAdapter();
        $corpus->setCorpusInStorage('firstname', ['fubar', 'dada']);
        $predict = new PredictFromTypo($corpus);
        $this::assertEquals(
            ['firstname' => 'Fubar', 'name' => 'Penaud'],
            $predict->predictNameFirstName('Fubar Penaud')
        );
    }
}
