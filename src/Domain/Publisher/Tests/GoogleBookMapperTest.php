<?php
declare(strict_types=1);

namespace App\Domain\Publisher\Tests;

use App\Domain\Publisher\GoogleBookMapper;
use PHPUnit\Framework\TestCase;
use Scriptotek\GoogleBooks\Volume;

class GoogleBookMapperTest extends TestCase
{

    public function testProcess()
    {
        $text = file_get_contents(__DIR__.'/googleBook.json');
        $json = json_decode($text);

        $volumeInfo = $json->items[0]->volumeInfo;
        $volume = new Volume('bla', $volumeInfo);

        $mapper = new GoogleBookMapper();
        $actual = $mapper->process($volume);

        $this::assertSame(
            [
                'auteur1' => 'Collectif',
                'auteur2' => null,
                'auteur3' => null,
                'titre' => 'Histoire de la Provence....',
                'sous-titre' => 'La Provence moderne, 1481-1800',
                'année' => '1991',
                'pages totales' => '',
                'isbn' => '9782737309526',
                'présentation en ligne' => null,
                'lire en ligne' => null,
            ],
            $actual
        );
    }
}
