<?php

namespace App\Domain\Publisher\Tests;

use App\Domain\Publisher\BnfMapper;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

class BnfMapperTest extends TestCase
{

    public function testProcess()
    {
        $text = file_get_contents(__DIR__.'/fixture_bnf.xml');

        $xml = new SimpleXMLElement($text);
        $xml->registerXPathNamespace('mxc', "info:lc/xmlns/marcxchange-v2");

        $mapper = new BnfMapper();
        $actual = $mapper->process($xml);
        $this::assertSame(
            [
                'bnf' => '35049657',
                'isbn' => '2-85319-209-1',
                'langue' => 'fr',
                'langue originale' => null,
                'langue titre' => null,
                'titre' => 'Dictionnaire des chanteurs francophones',
                'sous-titre' => 'de 1900 à nos jours',
                'auteur1' => 'Alain-Pierre Noyer',
                'auteur2' => null,
                'volume' => null,
                'collection' => null,
                'lieu' => 'Paris',
                'éditeur' => 'Conseil international de la langue française',
                'date' => '1989',
            ],
            $actual
        );
    }
}
