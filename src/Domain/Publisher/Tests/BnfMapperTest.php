<?php

declare(strict_types=1);

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
        $xml->registerXPathNamespace('mxc', 'info:lc/xmlns/marcxchange-v2');

        $mapper = new BnfMapper();
        $actual = $mapper->process($xml);
        $this::assertSame(
            [
                //'bnf' => '35049657',
                'isbn' => '2-85319-209-1',
                'isbn2' => '2-33333-209-1',
                'langue' => 'fr',
                'langue originale' => 'it',
                'langue titre' => null,
                'titre' => 'Dictionnaire des chanteurs francophones',
                'titre original' => null,
                'sous-titre' => 'de 1900 à nos jours', //, 900 biographies d\'interprètes, 6000 titres de chansons',
                'auteur1' => 'Alain-Pierre Noyer',
                'auteur2' => null,
                'volume' => null,
                'collection' => null,
                'lieu' => 'Paris/Saint-Denis',
                'éditeur' => 'Conseil international de la langue française / Université de la Réunion',
                'date' => '1996',
                'pages totales' => '622',
                'infos' => [
                    'source' => 'BnF',
                    'sourceTag' => 'BnF:2019',
                    'bnfAuteur1' => '12136586',
                    'ISNIAuteur1' => '0000 0003 6089 3659',
                    'yearsAuteur1' => '1948-....',
                ],
            ],
            $actual
        );
    }

    public function testProcessIsbnRectifie()
    {
        $text = file_get_contents(__DIR__.'/bnf_multi_isbn_rectifie.xml');

        $xml = new SimpleXMLElement($text);
        $xml->registerXPathNamespace('mxc', 'info:lc/xmlns/marcxchange-v2');

        $mapper = new BnfMapper();
        $actual = $mapper->process($xml);
        $this::assertSame(
            [],
            $actual
        );
    }
}
