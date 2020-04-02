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
use App\Domain\WikiTemplateFactory;
use Exception;
use PHPUnit\Framework\TestCase;

class AbstractWikiTemplateTest extends TestCase
{
    public function testOuvrageSerialize()
    {
        $ouvrage = WikiTemplateFactory::create('ouvrage');
        $ouvrage->hydrate(
            [
                'nom1' => 'Michou',
                'prénom1' => 'Bob',
                'titre' => 'Au soleil',
            ]
        );
        $ouvrage->setParam('auteur2', 'Sophie');
        $this::assertSame(
            '{{Ouvrage|nom1=Michou|auteur2=Sophie|prénom1=Bob|titre=Au soleil|éditeur=|année=|pages totales=|isbn=}}',
            $ouvrage->serialize()
        );
        $this::assertSame(
            '{{Ouvrage|prénom1=Bob|nom1=Michou|auteur2=Sophie|titre=Au soleil|éditeur=|année=|pages totales=|isbn=}}',
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

        $lienWeb = WikiTemplateFactory::create('lien web');
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

        $lienWeb->userMultiSpaced = true;
        $lienWeb->userSeparator = "\n|";
        $this::assertEquals(
            '{{lien web
|url         = http://google.com
|auteur1     = Bob
|date        = 2010-11-25
|consulté le = 
|titre       = Foo bar
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

        $lienWeb = WikiTemplateFactory::create('lien web');
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
    //        $lienWeb = \App\Domain\WikiTemplateFactory::create('lien web');
    //        $this::expectException(\Exception::class);
    //        // no parameter "fu" in template "lien web"
    //        $lienWeb->hydrate($data);
    //    }

    public function testAliasParameter()
    {
        $lienWeb = WikiTemplateFactory::create('lien web');
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
        $lienWeb = WikiTemplateFactory::create('lien web');
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

        $lienWeb = WikiTemplateFactory::create('lien web');
        $lienWeb->hydrate($data);

        $lienWeb->setParam('url', '');

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

        $lienWeb = WikiTemplateFactory::create('lien web');
        $lienWeb->hydrate($data);
        $lienWeb->setParamOrderByUser(['url', 'langue', 'titre']);

        $this::assertEquals(
            '{{lien web|langue=fr|titre=|url=http://google.com|consulté le=}}',
            $lienWeb->serialize(true)
        );
    }
}
