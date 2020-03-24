<?php

declare(strict_types=1);

namespace App\Domain\Publisher\Tests;

use App\Domain\OuvrageOptimize;
use App\Domain\Publisher\Wikidata2Ouvrage;
use App\Domain\WikiTemplateFactory;
use App\Infrastructure\WikidataAdapter;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

include __DIR__.'/../../../Application/myBootstrap.php';

class Wikidata2OuvrageTest extends TestCase
{
    /**
     * @throws \Exception
     */
    public function testIntegrationComplete()
    {
        $this::markTestSkipped("test d'integration avec requete WIKIDATA");
        // besoin bootstrap user-agent pour requete ?

        $ouvrage = WikiTemplateFactory::create('ouvrage');
        $ouvrage->hydrateFromText('{{Ouvrage|auteur=Bob|titre=Ma vie|passage=407-408}}');
        // Houellebecq : La carte et le Territoire
        $ouvrage->setInfos(
            [
                'isbn' => '978-2-08-124633-1',
                'ISNIAuteur1' => '0000 0001 2137 320X',
            ]
        );

        $wikidataAdapter = new WikidataAdapter(
            new Client(['timeout' => 5, 'headers' => ['User-Agent' => getenv('USER_AGENT')]])
        );
        $convert = new Wikidata2Ouvrage($wikidataAdapter, $ouvrage);
        $wdOuvrage = $convert->getOuvrage();
        $this::assertSame(
            '{{Ouvrage|auteur1=Bob|lien auteur1=Michel Houellebecq|titre=Ma vie|lien titre=La Carte et le Territoire|éditeur=|année=|passage=407-408 |pages totales=|isbn=}}',
            $wdOuvrage->serialize(true)
        );

        // Après optimization
        $optimizer = new OuvrageOptimize($wdOuvrage, 'Bla');
        $optimizer->doTasks();
        $this::assertSame(
            '{{Ouvrage|auteur1=Bob|lien auteur1=Michel Houellebecq|titre=Ma vie|lien titre=La Carte et le Territoire|éditeur=|année=|passage=407-408 |pages totales=|isbn=}}',
            $optimizer->getOuvrage()->serialize(true)
        );
    }
}
