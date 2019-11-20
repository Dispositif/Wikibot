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
            // Editeur
            //            [
            //                ['éditeur' => 'Éd. de La Ville'],
            //                '{{Ouvrage|langue=|titre=|éditeur=La Ville|année=|pages totales=|isbn=}}',
            //            ],
            [
                ['éditeur' => '[[Fu]]'],
                '{{Ouvrage|langue=|titre=|éditeur=[[Fu]]|année=|pages totales=|isbn=}}',
            ],
            [
                ['éditeur' => '[[Fu|Bar]] bla'],
                '{{Ouvrage|langue=|titre=|éditeur=[[Fu|Bar]] bla|année=|pages totales=|isbn=}}',
            ],
            [
                ['éditeur' => 'bar', 'lien éditeur' => 'fu'],
                '{{Ouvrage|langue=|titre=|éditeur=[[Fu|Bar]]|année=|pages totales=|isbn=}}',
            ],
            [
                ['éditeur' => '[[Fu]] [[Bar]]'],
                '{{Ouvrage|langue=|titre=|éditeur=[[Fu]] [[Bar]]|année=|pages totales=|isbn=}}',
            ],

            // Lieu
            [
                ['lieu' => '[[paris]]'],
                '{{Ouvrage|langue=|titre=|lieu=Paris|éditeur=|année=|pages totales=|isbn=}}',
            ],
            [
                ['lieu' => 'London'],
                '{{Ouvrage|langue=|titre=|lieu=Londres|éditeur=|année=|pages totales=|isbn=}}',
            ],
            [
                ['lieu' => 'Fu'],
                '{{Ouvrage|langue=|titre=|lieu=Fu|éditeur=|année=|pages totales=|isbn=}}',
            ],
            // date
            [
                ['date' => '[[1995]]'],
                '{{Ouvrage|langue=|titre=|éditeur=|année=1995|pages totales=|isbn=}}',
            ],
            // bnf
            [
                ['bnf' => 'FRBNF30279779'],
                '{{Ouvrage|langue=|titre=|éditeur=|année=|pages totales=|isbn=|bnf=30279779}}',
            ],
        ];
    }

    public function testGetOuvrage()
    {
        $raw
            = '{{Ouvrage|languX=français|id=ZE|prénom1=Ernest|nom1=Nègre|titre=Toponymie:France|tome=3|page=15-27|isbn=2600028846}}';

        $parse = TemplateParser::parseAllTemplateByName('ouvrage', $raw);
        $origin = $parse['ouvrage'][0]['model'];

        $optimized = (new OuvrageOptimize($origin))->doTasks()->getOuvrage();
        $this::assertSame(
            '{{Ouvrage|id=ZE|langue=fr|prénom1=Ernest|nom1=Nègre|titre=Toponymie|sous-titre=France|tome=3|éditeur=|année=|pages totales=|isbn=978-2-600-02884-4|isbn10=2600028846|passage=15-27}}',
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
                '{{Ouvrage|langue=|titre=[[Fu:bar]]|éditeur=|année=|pages totales=|isbn=}}',
            ],
            [
                // [[titre]]
                ['title' => '[[Fubar]]'],
                '{{Ouvrage|langue=|titre=[[Fubar]]|éditeur=|année=|pages totales=|isbn=}}',
            ],
            [
                // {{lang}} + [[ ]]
                ['title' => '{{lang|en|[[Fubar]]}}'],
                '{{Ouvrage|langue=en|titre=[[Fubar]]|éditeur=|année=|pages totales=|isbn=}}',
            ],
            [
                // {{lang}}
                ['title' => '{{lang|en|fubar}}'],
                '{{Ouvrage|langue=en|titre=Fubar|éditeur=|année=|pages totales=|isbn=}}',
            ],
            [
                // lien externe -> déplacé
                ['title' => '[http://google.fr/bla Fubar]'],
                '{{Ouvrage|langue=|titre=Fubar|éditeur=|année=|pages totales=|isbn=|lire en ligne=http://google.fr/bla}}',
            ],
            [
                ['title' => 'Toponymie'],
                '{{Ouvrage|langue=|titre=Toponymie|éditeur=|année=|pages totales=|isbn=}}',
            ],
//            [
//                // Transform desactived
//                ['title' => 'Toponymie. France'],
//                '{{Ouvrage|langue=|titre=Toponymie|sous-titre=France|éditeur=|année=|pages totales=|isbn=}}',
//            ],
            [
                // Extraits des mémoires de M. le duc de Rovigo
                ['title' => 'Extraits des mémoires de M. le duc de Rovigo'],
                '{{Ouvrage|langue=|titre=Extraits des mémoires de M. le duc de Rovigo|éditeur=|année=|pages totales=|isbn=}}',
            ],
            [
                // inchangé (numbers)
                ['title' => 'Vive PHP 7.3 en short'],
                '{{Ouvrage|langue=|titre=Vive PHP 7.3 en short|éditeur=|année=|pages totales=|isbn=}}',
            ],
            [
                ['title' => 'Ils ont osé... Les maires de Saint-Camille'],
                '{{Ouvrage|langue=|titre=Ils ont osé... Les maires de Saint-Camille|éditeur=|année=|pages totales=|isbn=}}',
            ],
            [
                // explode "-" spaced)
                ['title' => 'Toponymie - france'],
                '{{Ouvrage|langue=|titre=Toponymie|sous-titre=france|éditeur=|année=|pages totales=|isbn=}}',
            ],
            [
                // explode "/" spaced)
                ['title' => 'Toponymie / France'],
                '{{Ouvrage|langue=|titre=Toponymie|sous-titre=France|éditeur=|année=|pages totales=|isbn=}}',
            ],
            [
                // inchangé
                ['title' => 'Toponymie Jean-Pierre France'],
                '{{Ouvrage|langue=|titre=Toponymie Jean-Pierre France|éditeur=|année=|pages totales=|isbn=}}',
            ],
            [
                // inchangé
                ['title' => 'Toponymie 1914-1918 super'],
                '{{Ouvrage|langue=|titre=Toponymie 1914-1918 super|éditeur=|année=|pages totales=|isbn=}}',
            ],
        ];
    }

    public function testProcessIsbnLangFromISBN()
    {
        $raw
            = '{{Ouvrage|isbn=2600028846}}';

        $parse = TemplateParser::parseAllTemplateByName('ouvrage', $raw);
        $origin = $parse['ouvrage'][0]['model'];

        $optimized = (new OuvrageOptimize($origin))->doTasks()->getOuvrage();
        $this::assertSame(
            '{{Ouvrage|langue=fr|titre=|éditeur=|année=|pages totales=|isbn=978-2-600-02884-4|isbn10=2600028846}}',
            $optimized->serialize(true)
        );
    }

    public function testDistinguishAuthors()
    {
        $ouvrage = new OuvrageTemplate();
        $ouvrage->hydrateFromText('{{ouvrage|auteur=Marie Durand, Pierre Berger, Francois Morgand|titre=Bla}}');

        $optimizer = (new OuvrageOptimize($ouvrage))->doTasks();
        $final = $optimizer->getOuvrage();

        $this::assertSame(
            '{{Ouvrage|langue=|auteur1=Marie Durand|auteur2=Pierre Berger|auteur3=Francois Morgand|titre=Bla|éditeur=|année=|pages totales=|isbn=}}',
            $final->serialize(true)
        );
    }
}
