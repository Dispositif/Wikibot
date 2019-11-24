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
                [
                    'commentaire' => 'bla',
                    'plume' => 'oui',
                ],
                '{{Ouvrage|titre=|éditeur=|année=|isbn=}}{{nobr|. {{plume}}}}{{commentaire biblio|bla}}',
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
                ['éditeur' => '[[Fu]]'],
                '{{Ouvrage|titre=|éditeur=[[Fu]]|année=|isbn=}}',
            ],
            [
                ['éditeur' => '[[Fu|Bar]] bla'],
                '{{Ouvrage|titre=|éditeur=[[Fu|Bar]] bla|année=|isbn=}}',
            ],
            [
                ['éditeur' => 'bar', 'lien éditeur' => 'fu'],
                '{{Ouvrage|titre=|éditeur=[[Fu|Bar]]|année=|isbn=}}',
            ],
            [
                ['éditeur' => '[[Fu]] [[Bar]]'],
                '{{Ouvrage|titre=|éditeur=[[Fu]] [[Bar]]|année=|isbn=}}',
            ],
            // Lieu
            [
                ['lieu' => '[[paris]]'],
                '{{Ouvrage|titre=|lieu=Paris|éditeur=|année=|isbn=}}',
            ],
            [
                ['lieu' => 'London'],
                '{{Ouvrage|titre=|lieu=Londres|éditeur=|année=|isbn=}}',
            ],
            [
                ['lieu' => 'Köln'],
                '{{Ouvrage|titre=|lieu=Cologne|éditeur=|année=|isbn=}}',
            ],
            [
                ['lieu' => 'Fu'],
                '{{Ouvrage|titre=|lieu=Fu|éditeur=|année=|isbn=}}',
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
            = '{{Ouvrage|languX=anglais|id=ZE|prénom1=Ernest|nom1=Nègre|titre=Toponymie:France|tome=3|page=15-27|isbn=2600028846}}';

        $parse = TemplateParser::parseAllTemplateByName('ouvrage', $raw);
        $origin = $parse['ouvrage'][0]['model'];

        $optimized = (new OuvrageOptimize($origin))->doTasks()->getOuvrage();
        $this::assertSame(
            '{{Ouvrage|id=ZE|langue=en|prénom1=Ernest|nom1=Nègre|titre=Toponymie|sous-titre=France|tome=3|éditeur=|année=|isbn=978-2-600-02884-4|isbn10=2600028846|passage=15-27}}',
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
            [
                // {{lang}} + [[ ]]
                ['title' => '{{lang|en|[[Fubar]]}}'],
                '{{Ouvrage|langue=en|titre=[[Fubar]]|éditeur=|année=|isbn=}}',
            ],
            [
                // {{lang}}
                ['title' => '{{lang|en|fubar}}'],
                '{{Ouvrage|langue=en|titre=Fubar|éditeur=|année=|isbn=}}',
            ],
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
     * @throws Exception
     */
    public function testIsbn($isbn, $expected)
    {
        $origin = new OuvrageTemplate();
        $origin->hydrate(['isbn' => $isbn]);

        $optimized = (new OuvrageOptimize($origin))->doTasks()->getOuvrage();
        $this::assertSame(
            $expected,
            $optimized->serialize(true)
        );
    }

    public function provideISBN()
    {
        return [
            ['978-2-600-02884-4', '{{Ouvrage|titre=|éditeur=|année=|isbn=978-2-600-02884-4}}'],
            // isbn10
            [
                '2706812516',
                '{{Ouvrage|titre=|éditeur=|année=|isbn=978-2-7068-1251-4|isbn10=2706812516}}',
            ],
            // isbn invalide (clé vérification)
            ['978-2-600-02884-0', '{{Ouvrage|titre=|éditeur=|année=|isbn=978-2-600-02884-4}}'],
            // isbn invalide
            [
                '978-2-600-028-0',
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
