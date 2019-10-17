<?php

declare(strict_types=1);

namespace App\Domain\Models\Wiki;

use Exception;
use PHPUnit\Framework\TestCase;

class AbstractWikiTemplateTest extends TestCase
{
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

        $actual = $lienWeb->serialize();

        $this::assertEquals(
            '{{lien web|url=http://google.com|auteur1=Bob|date=2010-11-25|titre=Foo bar|consulté le=}}',
            $actual
        );

        $lienWeb->userSeparator = "\n|";
        $this::assertEquals(
            '{{lien web
|url=http://google.com
|auteur1=Bob
|date=2010-11-25
|titre=Foo bar
|consulté le=}}',
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
                'consulté le' => '',
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
            '{{lien web|url=|titre=|consulté le=}}',
            $lienWeb->serialize()
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
            '{{lien web|url=http://google.com|langue=fr|titre=|consulté le=}}',
            $lienWeb->serialize()
        );
    }
}
