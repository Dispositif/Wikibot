<?php

declare(strict_types=1);

namespace App\Domain\Publisher\Tests;

use App\Application\PublisherAction;
use App\Domain\Publisher\FigaroMapper;
use App\Domain\Publisher\LeMondeMapper;
use App\Domain\Publisher\LiberationMapper;
use PHPUnit\Framework\TestCase;

include __DIR__.'/../../../Application/myBootstrap.php';

class WebMapperTest extends TestCase
{
    public function testFigaroMapper()
    {
        $html = file_get_contents(__DIR__.'/fixture_news_figaro.html');

        $publiAction = new PublisherAction('bla');
        $htmlData = $publiAction->extractWebData($html);

        $mapper = new FigaroMapper();
        $actual = $mapper->process($htmlData);

        $this::assertSame(
            [
                'périodique' => '[[Le Figaro]]',
                'titre' => 'Face au Covid-19, les cliniques privées mobilisées… mais en manque de masques',
                'lire en ligne' => 'http://www.lefigaro.fr/sciences/face-au-covid-19-les-cliniques-privees-mobilisees-mais-en-manque-de-masques-20200318',
                'date' => '18-03-2020',
                'auteur1' => 'Marie-Cécile Renault',
                'auteur2' => null,
                'auteur3' => null,
                'auteur institutionnel' => null,
            ],
            $actual
        );
    }

    public function testLeMondeMapper()
    {
        $html = file_get_contents(__DIR__.'/fixture_news_lemonde.html');

        $publiAction = new PublisherAction('bla');
        $htmlData = $publiAction->extractWebData($html);

        $mapper = new LeMondeMapper();
        $actual = $mapper->process($htmlData);

        $this::assertSame(
            [
                'périodique' => '[[Le Monde]]',
                'titre' => 'Coronavirus : la Californie placée à son tour en confinement',
                'lire en ligne' => 'https://www.lemonde.fr/planete/article/2020/03/20/coronavirus-la-californie-placee-en-confinement_6033754_3244.html',
                'auteur1' => 'Le Monde avec AFP',
                'date' => '20-03-2020',
            ],
            $actual
        );
    }

    public function testLiberationMapper()
    {
        $html = file_get_contents(__DIR__.'/fixture_news_liberation.html');

        $publiAction = new PublisherAction('bla');
        $htmlData = $publiAction->extractWebData($html);

        $mapper = new LiberationMapper();
        $actual = $mapper->process($htmlData);

        $this::assertSame(
            [
                'périodique' => '[[Libération (journal)|Libération]]',
                'titre' => 'En Bretagne, le Parisiens-bashing guette',
                'lire en ligne' => 'https://www.liberation.fr/france/2020/03/20/en-bretagne-le-parisiens-bashing-guette_1782471',
                'date' => '20-03-2020',
                'auteur1' => 'Pierre-Henri Allain',
                'auteur2' => null,
                'auteur3' => null,
                'auteur institutionnel' => null,
            ],
            $actual
        );
    }
}
