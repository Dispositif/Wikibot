<?php

///**
// * This file is part of dispositif/wikibot application
// * 2019 : Philippe M. <dispositif@gmail.com>
// * For the full copyright and MIT license information, please view the LICENSE file.
// */
//
//declare(strict_types=1);
//
//namespace App\Domain;
//
//use App\Infrastructure\CorpusAdapter;
//use PHPUnit\Framework\TestCase;
//
//class PredictNameFirstNameTest extends TestCase
//{
//    /**
//     * @dataProvider authorProvider
//     *
//     * @param $author
//     * @param $expected
//     */
//    public function testPredictNameFirstName($author, $expected)
//    {
//        $corpus = new CorpusAdapter();
//        // lowercap !!!!!
//        $corpus->setCorpusInStorage(
//            'firstname',
//            [
//                'totoro',
//                'pierre',
//                'paul',
//                'jean',
//            ]
//        );
//
//        $pred = new PredictNameFirstName($corpus);
//
//        //        $predict = new TypoTokenizer($corpus);
//        $this::assertEquals(
//            $expected,
//            $pred->predictNameFirstName($author)
//        );
//    }
//
//    public function authorProvider()
//    {
//        return [
//            ['Totoro Penaud', ['firstname' => 'Totoro', 'name' => 'Penaud']],
//            ['Jean-Pierre Penaud', ['firstname' => 'Jean-Pierre', 'name' => 'Penaud']],
//            ['J. Penaud', ['firstname' => 'J.', 'name' => 'Penaud']],
//            ['Penaud, J.', ['firstname' => 'J.', 'name' => 'Penaud']],
//            ['A. Durand', ['firstname' => 'A.', 'name' => 'Durand']],
//            ['A. B. Durand', ['fail' => 'unknown typo pattern', 'pattern' => 'INITIAL INITIAL FIRSTUPPER']],
//            ['Pierre Durand, Paul Marchal', ['fail' => '2+ authors in string']],
//            ['Babar Elephant', ['fail' => 'firstname not in corpus']],
//        ];
//    }
//
//    public function testWithStorageCorpus()
//    {
//        $corpus = new CorpusAdapter();
//        $corpus->setCorpusInStorage('firstname', ['fubar', 'dada']);
//        $predict = new PredictNameFirstName($corpus);
//        $this::assertEquals(
//            ['firstname' => 'Fubar', 'name' => 'Penaud'],
//            $predict->predictNameFirstName('Fubar Penaud')
//        );
//    }
//}
