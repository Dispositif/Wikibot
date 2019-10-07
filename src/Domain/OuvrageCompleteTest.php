<?php

namespace App\Domain;


use App\Domain\Models\Wiki\OuvrageClean;
use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\Utils\WikiTextUtil;
use PHPUnit\Framework\TestCase;

class OuvrageCompleteTest extends TestCase
{

    public function testGetResult()
    {
        $origin = new OuvrageTemplate();
        $origin->hydrateFromText('{{Ouvrage |id =Bonneton|nom1=Collectif | titre = Loiret : un département à l\'élégance naturelle | éditeur = Christine Bonneton | lieu = Paris | année = 2 septembre 1998 | isbn = 978-2-86253-234-9| pages totales = 319}}');

        $google = new OuvrageClean();
        $google->hydrateFromText('{{ouvrage|langue=fr|auteur1=Clément Borgal|titre=Loiret|année=1998|pages totales=319|isbn=9782862532349}}');

        $comp = new OuvrageComplete($origin, $google);
        $this::assertEquals(
            '{{ouvrage |identifiant=Bonneton |nom1=Collectif |titre=Loiret : un département à l\'élégance naturelle |éditeur=Christine Bonneton |lieu=Paris |année=2 septembre 1998 |isbn=978-2-86253-234-9 |pages totales=319 |langue=fr}}',
            $comp->getResult()->serialize()
        );
    }


}
