<?php

namespace App\Domain\Models\Wiki;

use PHPUnit\Framework\TestCase;

class LienWebTemplateTest extends TestCase
{

    public function testSerialize()
    {
        $data = [
            'url' => 'http://google.com',
            'auteur1' => 'Bob',
            'date' => '2010-11-25',
            'titre' => 'Foo bar',
        ];

        $lienWeb = new LienWebTemplate();
        $lienWeb->hydrate($data);

        $actual = $lienWeb->serialize();

        $this::assertEquals(
            '{{lien web|langue=|auteur1=Bob|titre=Foo bar|url=http://google.com|site=|date=2010-11-25|consulté le=}}',
            $actual
        );
    }

}
