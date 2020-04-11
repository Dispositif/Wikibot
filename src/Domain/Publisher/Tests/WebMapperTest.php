<?php

declare(strict_types=1);

namespace App\Domain\Publisher\Tests;

use App\Application\PublisherAction;
use App\Domain\Publisher\WebMapper;
use PHPUnit\Framework\TestCase;

class WebMapperTest extends TestCase
{

    /**
     * @dataProvider provideMappingFromFile
     *
     * @param       $filename
     * @param array $expected
     *
     * @return void
     * @throws \Exception
     */
    public function testMappingProcess($filename, array $expected): void
    {
        $html = file_get_contents($filename);

        $publiAction = new PublisherAction('bla');
        $htmlData = $publiAction->extractWebData($html);
        $mapper = new WebMapper();

        $this::assertSame($expected, $mapper->process($htmlData));
    }

    public function provideMappingFromFile()
    {
        return [
            [
                __DIR__.'/fixture_web_figaro.html',
                [
                    'DATA-TYPE' => 'JSON-LD',
                    'DATA-ARTICLE' => true,
                    'périodique' => 'Le Figaro',
                    'titre' => 'Face au Covid-19, les cliniques privées mobilisées… mais en manque de masques',
                    'url' => 'http://www.lefigaro.fr/sciences/face-au-covid-19-les-cliniques-privees-mobilisees-mais-en-manque-de-masques-20200318',
                    'date' => '18-03-2020',
                    'auteur1' => 'Marie-Cécile Renault',
                    'url-access' => 'limité',
                ],
            ],
            [
                __DIR__.'/fixture_web_lemonde.html',
                [
                    'DATA-TYPE' => 'JSON-LD',
                    'DATA-ARTICLE' => true,
                    'périodique' => 'Le Monde',
                    'titre' => 'Coronavirus : la Californie placée à son tour en confinement',
                    'url' => 'https://www.lemonde.fr/planete/article/2020/03/20/coronavirus-la-californie-placee-en-confinement_6033754_3244.html',
                    'date' => '20-03-2020',
                ],
            ],
            [
                __DIR__.'/fixture_web_liberation.html',
                [
                    'DATA-TYPE' => 'JSON-LD',
                    'DATA-ARTICLE' => true,
                    'périodique' => 'Libération',
                    'titre' => 'En Bretagne, le Parisiens-bashing guette',
                    'url' => 'https://www.liberation.fr/france/2020/03/20/en-bretagne-le-parisiens-bashing-guette_1782471',
                    'date' => '20-03-2020',
                    'auteur1' => 'Pierre-Henri Allain',
                    'url-access' => 'ouvert',
                ],
            ],
        ];
    }

}
