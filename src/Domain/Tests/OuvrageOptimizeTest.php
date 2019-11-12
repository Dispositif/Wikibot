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
     * @dataProvider provideLocation
     *
     * @param $data
     * @param $expected
     *
     * @throws Exception
     */
    public function testLocation($data, $expected)
    {
        $ouvrage = new OuvrageTemplate();
        $ouvrage->hydrate($data);

        $optimized = (new OuvrageOptimize($ouvrage))->doTasks()->getOuvrage();
        $this::assertSame(
            $expected,
            $optimized->serialize(true)
        );
    }

    public function provideLocation()
    {
        return [
            [
                ['lieu' => 'London'],
                '{{Ouvrage|langue=|titre=|lieu=Londres|éditeur=|année=|pages totales=|isbn=}}',
            ],
            [
                ['lieu' => 'Fu'],
                '{{Ouvrage|langue=|titre=|lieu=Fu|éditeur=|année=|pages totales=|isbn=}}',
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
                ['title' => 'Toponymie'],
                '{{Ouvrage|langue=|titre=Toponymie|éditeur=|année=|pages totales=|isbn=}}',
            ],
            [
                ['title' => 'Toponymie. France'],
                '{{Ouvrage|langue=|titre=Toponymie|sous-titre=France|éditeur=|année=|pages totales=|isbn=}}',
            ], // explode
            [
                ['title' => 'Vive PHP 7.3 en short'],
                '{{Ouvrage|langue=|titre=Vive PHP 7.3 en short|éditeur=|année=|pages totales=|isbn=}}',
            ], //inchangé (numbers)
            [
                ['title' => 'Ils ont osé... Les maires de Saint-Camille'],
                '{{Ouvrage|langue=|titre=Ils ont osé... Les maires de Saint-Camille|éditeur=|année=|pages totales=|isbn=}}',
            ],
            [
                ['title' => 'Toponymie - france'],
                '{{Ouvrage|langue=|titre=Toponymie|sous-titre=France|éditeur=|année=|pages totales=|isbn=}}',
            ], // explode (- spaced)
            [
                ['title' => 'Toponymie / France'],
                '{{Ouvrage|langue=|titre=Toponymie|sous-titre=France|éditeur=|année=|pages totales=|isbn=}}',
            ], // explode (/ spaced)
            [
                ['title' => 'Toponymie Jean-Pierre France'],
                '{{Ouvrage|langue=|titre=Toponymie Jean-Pierre France|éditeur=|année=|pages totales=|isbn=}}',
            ], // inchangé
            [
                ['title' => 'Toponymie 1914-1918 super'],
                '{{Ouvrage|langue=|titre=Toponymie 1914-1918 super|éditeur=|année=|pages totales=|isbn=}}',
            ], // inchangé
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
