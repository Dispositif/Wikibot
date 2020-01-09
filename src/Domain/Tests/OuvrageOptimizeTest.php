<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Tests;

use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\OuvrageOptimize;
use App\Domain\Utils\TemplateParser;
use Exception;
use PHPUnit\Framework\TestCase;

class OuvrageOptimizeTest extends TestCase
{
    /**
     * @dataProvider provideSomeParam
     *
     * @param $data
     * @param $expected
     *
     * @throws Exception
     */
    public function testSomeParam($data, $expected)
    {
        $ouvrage = new OuvrageTemplate();
        $ouvrage->hydrate($data);

        $optimized = (new OuvrageOptimize($ouvrage))->doTasks()->getOuvrage();
        $this::assertSame(
            $expected,
            $optimized->serialize(true)
        );
    }

    public function provideSomeParam()
    {
        return [
            [
                // prédiction paramètre
                ['citation' => 'blabla'],
                '{{Ouvrage|titre=|éditeur=|année=|isbn=}}{{Citation bloc|blabla}}',
            ],
            [
                // année vide retirée si date=bla
                ['date' => '12-12-2018'],
                '{{Ouvrage|titre=|éditeur=|date=12-12-2018|isbn=}}',
            ],
            [
                // prédiction paramètre
                ['autuer' => 'bob'],
                '{{Ouvrage|auteur1=bob|titre=|éditeur=|année=|isbn=}}',
            ],
            [
                // CONVERT_GOOGLEBOOK_TEMPLATE = false;
                ['lire en ligne' => 'https://books.google.fr/books?id=3KNeP3Hm0TAC&pg=PA184&lpg=PA184&dq=apolline+de+Gourlet&source=bl&ots=bA3f27YKbl&sig=0EVHZ6yHKLBRTw-VgKwekQT7YZQ&hl=fr&sa=X&ved=2ahUKEwiLpNXY9pLfAhUH1hoKHa0EDy84ChDoATACegQIBRAB#v=onepage&q=apolline%20de%20Gourlet&f=false'],
                //'{{Ouvrage|titre=|éditeur=|année=|isbn=|lire en ligne={{Google
                // Livres|3KNeP3Hm0TAC|page=184|surligne=apolline+de+Gourlet}}}}'
                '{{Ouvrage|titre=|éditeur=|année=|isbn=|lire en ligne=https://books.google.fr/books?id=3KNeP3Hm0TAC&pg=PA184&dq=apolline+de+Gourlet}}',
            ],
            [
                [
                    'commentaire' => 'bla',
                    'plume' => 'oui',
                ],
                '{{Ouvrage|titre=|éditeur=|année=|isbn=|plume=oui}}{{Commentaire biblio|bla}}',
            ],
            [
                // langue FR : HOTFIX 22 nov 2019 "ne retire pas langue=fr" ajouté par humain
                ['langue' => 'Français'],
                '{{Ouvrage|langue=fr|titre=|éditeur=|année=|isbn=}}',
            ],
            [
                // langue FR
                ['langue' => 'Anglais'],
                '{{Ouvrage|langue=en|titre=|éditeur=|année=|isbn=}}',
            ],
            [
                // langue originale
                ['langue originale' => 'Anglais'],
                '{{Ouvrage|langue originale=en|titre=|éditeur=|année=|isbn=}}',
            ],
            [
                // 'langue originale' FR retirée si 'langue' = fr ou vide
                ['langue originale' => 'fr'],
                '{{Ouvrage|titre=|éditeur=|année=|isbn=}}',
            ],
            [
                ['éditeur' => '[[Fu]]'],
                '{{Ouvrage|titre=|éditeur=[[Fu]]|année=|isbn=}}',
            ],
            [
                ['éditeur' => '[[Fu|Bar]] bla'],
                '{{Ouvrage|titre=|éditeur=[[Fu|Bar]] bla|année=|isbn=}}',
            ],
            [
                ['éditeur' => 'bar', 'lien éditeur' => 'fu'],
                '{{Ouvrage|titre=|éditeur=[[fu|bar]]|année=|isbn=}}',
            ],
            [
                ['éditeur' => '[[Fu]] [[Bar]]'],
                '{{Ouvrage|titre=|éditeur=[[Fu]] [[Bar]]|année=|isbn=}}',
            ],
            // Lieu
            [
                ['lieu' => '[[paris]]'],
                '{{Ouvrage|titre=|éditeur=|lieu=Paris|année=|isbn=}}',
            ],
            [
                ['lieu' => 'London'],
                '{{Ouvrage|titre=|éditeur=|lieu=Londres|année=|isbn=}}',
            ],
            [
                ['lieu' => 'Köln'],
                '{{Ouvrage|titre=|éditeur=|lieu=Cologne|année=|isbn=}}',
            ],
            [
                ['lieu' => 'Fu'],
                '{{Ouvrage|titre=|éditeur=|lieu=Fu|année=|isbn=}}',
            ],
            [
                // date
                ['date' => '[[1995]]'],
                '{{Ouvrage|titre=|éditeur=|année=1995|isbn=}}',
            ],
            [
                // bnf
                ['bnf' => 'FRBNF30279779'],
                '{{Ouvrage|titre=|éditeur=|année=|isbn=|bnf=30279779}}',
            ],
        ];
    }

    public function testGetOuvrage()
    {
        $raw
            = '{{Ouvrage|languX=anglais|id=ZE|prénom1=Ernest|nom1=Nègre|nom2|titre=Toponymie:France|tome=3|passage=15-27|isbn=2600028846}}';

        $parse = TemplateParser::parseAllTemplateByName('ouvrage', $raw);
        $origin = $parse['ouvrage'][0]['model'];

        $optimized = (new OuvrageOptimize($origin))->doTasks()->getOuvrage();
        $this::assertSame(
            '{{Ouvrage|langue=en|prénom1=Ernest|nom1=Nègre|titre=Toponymie|sous-titre=France|tome=3|éditeur=|année=|passage=15-27|isbn=978-2-600-02884-4|isbn2=2-600-02884-6|id=ZE}}',
            $optimized->serialize(true)
        );
    }

    /**
     * @dataProvider provideProcessTitle
     *
     * @param $data
     * @param $expected
     *
     * @throws Exception
     */
    public function testProcessTitle($data, $expected)
    {
        $ouvrage = new OuvrageTemplate();
        $ouvrage->hydrate($data);

        $optimized = (new OuvrageOptimize($ouvrage))->doTasks()->getOuvrage();
        $this::assertSame(
            $expected,
            $optimized->serialize(true)
        );
    }

    public function provideProcessTitle()
    {
        return [
//            [
//                // tome/volume en romain
//                ['tome' => '4', 'volume' => '34'],
//                '{{Ouvrage|titre=|volume=34|tome=4|éditeur=|année=|isbn=}}',
//            ],
//            [
//                // tome/volume bizarre
//                ['tome' => '4c', 'volume' => 'E'],
//                '{{Ouvrage|titre=|volume=E|tome=4c|éditeur=|année=|isbn=}}',
//            ],
            [
                // bug 17 nov [[titre:sous-titre]]
                ['title' => '[[Fu:bar]]'],
                '{{Ouvrage|titre=[[Fu:bar]]|éditeur=|année=|isbn=}}',
            ],
            [
                // [[titre]]
                ['title' => '[[Fubar]]'],
                '{{Ouvrage|titre=[[Fubar]]|éditeur=|année=|isbn=}}',
            ],
//            [
//                // desactivé (livre FR avec titre EN)
//                // {{lang}} + [[ ]]
//                ['title' => '{{lang|en|[[Fubar]]}}'],
//                '{{Ouvrage|langue=en|titre=[[Fubar]]|éditeur=|année=|isbn=}}',
//            ],
//            [
//                // desactivé (livre FR avec titre EN)
//                // {{lang}}
//                ['title' => '{{lang|en|fubar}}'],
//                '{{Ouvrage|langue=en|titre=Fubar|éditeur=|année=|isbn=}}',
//            ],
            [
                // lien externe -> déplacé
                ['title' => '[http://google.fr/bla Fubar]'],
                '{{Ouvrage|titre=Fubar|éditeur=|année=|isbn=|lire en ligne=http://google.fr/bla}}',
            ],
            [
                ['title' => 'Toponymie'],
                '{{Ouvrage|titre=Toponymie|éditeur=|année=|isbn=}}',
            ],
            [
                // Extraits des mémoires de M. le duc de Rovigo
                ['title' => 'Extraits des mémoires de M. le duc de Rovigo'],
                '{{Ouvrage|titre=Extraits des mémoires de M. le duc de Rovigo|éditeur=|année=|isbn=}}',
            ],
            [
                // inchangé (numbers)
                ['title' => 'Vive PHP 7.3 en short'],
                '{{Ouvrage|titre=Vive PHP 7.3 en short|éditeur=|année=|isbn=}}',
            ],
            [
                ['title' => 'Ils ont osé... Les maires de Saint-Camille'],
                '{{Ouvrage|titre=Ils ont osé... Les maires de Saint-Camille|éditeur=|année=|isbn=}}',
            ],
            [
                // explode "-" spaced)
                ['title' => 'Toponymie - france'],
                '{{Ouvrage|titre=Toponymie|sous-titre=france|éditeur=|année=|isbn=}}',
            ],
            [
                // explode "/" spaced)
                ['title' => 'Toponymie / France'],
                '{{Ouvrage|titre=Toponymie|sous-titre=France|éditeur=|année=|isbn=}}',
            ],
            [
                // inchangé
                ['title' => 'Toponymie Jean-Pierre France'],
                '{{Ouvrage|titre=Toponymie Jean-Pierre France|éditeur=|année=|isbn=}}',
            ],
            [
                // inchangé
                ['title' => 'Toponymie 1914-1918 super'],
                '{{Ouvrage|titre=Toponymie 1914-1918 super|éditeur=|année=|isbn=}}',
            ],
        ];
    }

    /**
     * @dataProvider provideISBN
     *
     * @param $isbn
     * @param $expected
     *
     * @throws Exception
     */
    public function testIsbn(array $data, $expected)
    {
        $origin = new OuvrageTemplate();
        $origin->hydrate($data);

        $optimized = (new OuvrageOptimize($origin))->doTasks()->getOuvrage();
        $this::assertSame(
            $expected,
            $optimized->serialize(true)
        );
    }

    public function provideISBN()
    {
        return [
            [
                // bug iblis/isbn Mexican ISBN
                ['isbn'=>'970-07-6492-3'],
                '{{Ouvrage|titre=|éditeur=|année=|isbn=978-970-07-6492-4|isbn2=970-07-6492-3}}',
            ],
            [
                // no ISBN before 1970
                ['année' => '1950'],
                '{{Ouvrage|titre=|éditeur=|année=1950}}',
            ],
            [
                // empty 'isbn' after 1970
                ['année' => '1980'],
                '{{Ouvrage|titre=|éditeur=|année=1980|isbn=}}',
            ],
            [
                // isbn 13
                ['isbn' => '9782600028844'],
                '{{Ouvrage|titre=|éditeur=|année=|isbn=978-2-600-02884-4}}',
            ],
            [
                // isbn10
                ['isbn' => '2706812516'],
                '{{Ouvrage|titre=|éditeur=|année=|isbn=978-2-7068-1251-4|isbn2=2-7068-1251-6}}',
            ],
            [
                // isbn10 avant 2007
                ['isbn' => '2706812516', 'date' => 'octobre 1988'],
                '{{Ouvrage|titre=|éditeur=|date=octobre 1988|isbn=2-7068-1251-6}}',
            ],
            [
                // isbn10 avant 2007
                ['isbn' => '2706812516', 'année' => '1988'],
                '{{Ouvrage|titre=|éditeur=|année=1988|isbn=2-7068-1251-6}}',
            ],
            [
                // isbn=10 et isbn2=13
                ['isbn' => '2706812516', 'isbn2' => '9782706812514'],
                '{{Ouvrage|titre=|éditeur=|année=|isbn=978-2-7068-1251-4|isbn2=2-7068-1251-6}}',
            ],
            [
                // isbn=13 et isbn2=10
                ['isbn' => '9782706812514', 'isbn2' => '2-706812516'],
                '{{Ouvrage|titre=|éditeur=|année=|isbn=978-2-7068-1251-4|isbn2=2-706812516}}',
            ],
            [
                // isbn invalide (clé vérification)
                ['isbn' => '978-2-600-02884-0'],
                '{{Ouvrage|titre=|éditeur=|année=|isbn=978-2-600-02884-4}}',
            ],
            [
                // isbn invalide
                ['isbn' => '978-2-600-028-0'],
                '{{Ouvrage|titre=|éditeur=|année=|isbn=978-2-600-028-0|isbn invalide=978-2-600-028-0 trop court ou trop long}}',
            ],
        ];
    }

    public function testDistinguishAuthors()
    {
        $ouvrage = new OuvrageTemplate();
        $ouvrage->hydrateFromText('{{ouvrage|auteur=Marie Durand, Pierre Berger, Francois Morgand|titre=Bla}}');

        $optimizer = (new OuvrageOptimize($ouvrage))->doTasks();
        $final = $optimizer->getOuvrage();

        $this::assertSame(
            '{{Ouvrage|auteur1=Marie Durand|auteur2=Pierre Berger|auteur3=Francois Morgand|titre=Bla|éditeur=|année=|isbn=}}',
            $final->serialize(true)
        );
    }
}
