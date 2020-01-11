<?php

declare(strict_types=1);

namespace App\Domain\Publisher\Tests;

use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\OuvrageOptimize;
use App\Domain\Publisher\Wikidata2Ouvrage;
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

        $ouvrage = new OuvrageTemplate();
        $ouvrage->hydrateFromText('{{Ouvrage|auteur=Bob|titre=Ma vie|passage=407-408}}');
        // Houellebecq : La carte et le Territoire
        $ouvrage->setInfos(
            [
                'isbn' => '978-2-08-124633-1',
                'ISNIAuteur1' => '0000 0001 2137 320X',
            ]
        );

        $convert = new Wikidata2Ouvrage($ouvrage);
        $wdOuvrage = $convert->getOuvrage();
        $this::assertSame(
            '{{Ouvrage|auteur1=Bob|lien auteur1=Michel Houellebecq|titre=Ma vie|lien titre=La Carte et le Territoire|éditeur=|année=|passage=407-408|isbn=}}',
            $wdOuvrage->serialize(true)
        );

        // Après optimization
        $optimizer = new OuvrageOptimize($wdOuvrage, 'Bla');
        $optimizer->doTasks();
        $this::assertSame(
            '{{Ouvrage|auteur1=Bob|lien auteur1=Michel Houellebecq|titre=Ma vie|lien titre=La Carte et le Territoire|éditeur=|année=|passage=407-408|isbn=}}',
            $optimizer->getOuvrage()->serialize(true)
        );

    }
}
