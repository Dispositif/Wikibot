<?php

namespace App\Domain\Models\Wiki;

use PHPUnit\Framework\TestCase;

class LienWebTemplateTest extends TestCase
{

    public function testSerialize()
    {
        $data = [
            1 => 'fr',
            'url' => 'http://google.com',
            'auteur1' => 'Bob',
            'date' => '2010-11-25',
            'titre' => 'Foo bar',
        ];

        $lienWeb = new LienWebTemplate();
        $lienWeb->hydrate($data);

        $actual = $lienWeb->serialize();

        $this::assertEquals(
            '{{lien web|langue=fr|auteur1=Bob|titre=Foo bar|url=http://google.com|site=|date=2010-11-25|consulté le=}}',
            $actual
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
                'langue' => '',
                'site' => '',
                'consulté le' => '',
            ],
            $lienWeb->toArray()
        );
    }

    public function testUnknownParameter()
    {
        $data = [
            'fu' => 'bar',
        ];
        $lienWeb = new LienWebTemplate();
        $this::expectException(\Exception::class);
        // no parameter "fu" in template "lien web"
        $lienWeb->hydrate($data);
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
                'auteur2' => '' // optional parameter
            ]
        );

        $this::assertEquals(
            '{{lien web|langue=|titre=|url=|site=|date=|consulté le=}}',
            $lienWeb->serialize()
        );
    }

}
