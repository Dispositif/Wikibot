<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Models\Wiki\Tests;

use App\Domain\Models\Wiki\LienWebTemplate;
use App\Domain\Models\Wiki\OuvrageTemplate;
use Exception;
use PHPUnit\Framework\TestCase;

class AbstractWikiTemplateTest extends TestCase
{
    public function testOuvrageSerialize()
    {
        $ouvrage = new OuvrageTemplate();
        $ouvrage->hydrate(
            [
                'nom1' => 'Michou',
                'prénom1' => 'Bob',
                'titre' => 'Au soleil',
            ]
        );
        $ouvrage->setParam('auteur2', 'Sophie');
        $this::assertSame(
            '{{Ouvrage|nom1=Michou|auteur2=Sophie|prénom1=Bob|titre=Au soleil|éditeur=|année=|isbn=}}',
            $ouvrage->serialize()
        );
        $this::assertSame(
            '{{Ouvrage|prénom1=Bob|nom1=Michou|auteur2=Sophie|titre=Au soleil|éditeur=|année=|isbn=}}',
            $ouvrage->serialize(true)
        );
    }

    /**
     * @throws Exception
     */
    public function testSerialize()
    {
        $data = [
            //            '1' => 'fr',
            'url' => 'http://google.com',
            'auteur1' => 'Bob',
            'date' => '2010-11-25',
            'titre' => 'foo bar',
        ];

        $lienWeb = new LienWebTemplate();
        $lienWeb->hydrate($data);

        $this::assertEquals(
            '{{lien web|auteur1=Bob|titre=Foo bar|url=http://google.com|date=2010-11-25|consulté le=}}',
            $lienWeb->serialize(true)
        );

        $this::assertEquals(
            '{{lien web|url=http://google.com|auteur1=Bob|date=2010-11-25|consulté le=|titre=Foo bar}}',
            $lienWeb->serialize()
        );

        $lienWeb->userSeparator = "\n|";
        $this::assertEquals(
            '{{lien web
|url=http://google.com
|auteur1=Bob
|date=2010-11-25
|consulté le=
|titre=Foo bar
}}',
            $lienWeb->serialize()
        );
    }

    public function testToArray()
    {
        $data = [
            'url' => 'http://google.com',
            'auteur1' => 'Bob',
            'date' => '2010-11-25',
            'titre' => 'Foo bar',
        ];

        $lienWeb = new LienWebTemplate();
        $lienWeb->hydrate($data);
        $this::assertEquals(
            [
                'url' => 'http://google.com',
                'auteur1' => 'Bob',
                'date' => '2010-11-25',
                'titre' => 'Foo bar',
            ],
            $lienWeb->toArray()
        );
    }

    //    public function testUnknownParameter()
    //    {
    //        $data = [
    //            'fu' => 'bar',
    //        ];
    //        $lienWeb = new LienWebTemplate();
    //        $this::expectException(\Exception::class);
    //        // no parameter "fu" in template "lien web"
    //        $lienWeb->hydrate($data);
    //    }

    public function testAliasParameter()
    {
        $lienWeb = new LienWebTemplate();
        $lienWeb->hydrate(
            [
                'lang' => 'fr',
            ]
        );
        $this::assertEquals(
            '{{lien web|langue=fr|titre=|url=|consulté le=}}',
            $lienWeb->serialize()
        );
    }

    public function testMagicGetter()
    {
        $lienWeb = new LienWebTemplate();
        $lienWeb->hydrate(
            [
                'url' => 'bla',
            ]
        );
        $this::assertEquals(
            'bla',
            $lienWeb->url
        );
    }

    public function testEmptyValue()
    {
        $data = [
            'url' => 'http://google.com',
        ];

        $lienWeb = new LienWebTemplate();
        $lienWeb->hydrate($data);

        $lienWeb->hydrate(
            [
                'url' => '', // default parameter
                'auteur2' => '', // optional parameter
            ]
        );

        $this::assertEquals(
            '{{lien web|titre=|url=|consulté le=}}',
            $lienWeb->serialize(true)
        );
    }

    public function testUserOrder()
    {
        $data = [
            'url' => 'http://google.com',
            'langue' => 'fr',
        ];

        $lienWeb = new LienWebTemplate();
        $lienWeb->hydrate($data);
        $lienWeb->setParamOrderByUser(['url', 'langue', 'titre']);

        $this::assertEquals(
            '{{lien web|langue=fr|titre=|url=http://google.com|consulté le=}}',
            $lienWeb->serialize(true)
        );
    }
}
