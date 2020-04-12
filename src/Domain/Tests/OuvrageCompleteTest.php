<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Tests;

use App\Domain\Models\Wiki\OuvrageClean;
use App\Domain\OuvrageComplete;
use App\Domain\WikiTemplateFactory;
use Exception;
use PHPUnit\Framework\TestCase;

class OuvrageCompleteTest extends TestCase
{
    public function testGetResult()
    {
        $origin = WikiTemplateFactory::create('ouvrage');
        $origin->hydrateFromText(
            '{{Ouvrage |id =Bonneton|nom1=Collectif | titre = Loiret : un département à l\'élégance naturelle | éditeur = Christine Bonneton | lieu = Paris | année = 2 septembre 1998 | isbn = 978-2-86253-234-9| pages totales = 319 }}'
        );

        $google = WikiTemplateFactory::create('ouvrage');
        $google->hydrateFromText(
            '{{ouvrage|langue=fr|auteur1=Clément Borgal|titre=Loiret|année=1998|pages totales=319|isbn=9782862532349}}'
        );

        $comp = new OuvrageComplete($origin, $google);
        $this::assertEquals(
            '{{Ouvrage |langue=fr |id=Bonneton |nom1=Collectif |titre=Loiret : un département à l\'élégance naturelle |éditeur=Christine Bonneton |lieu=Paris |année=2 septembre 1998 |isbn=978-2-86253-234-9 |pages totales=319}}',
            $comp->getResult()->serialize()
        );
    }

    /**
     * @dataProvider provideComplete
     *
     * @param string $originStr
     * @param string $onlineStr
     * @param string $expected
     *
     * @throws Exception
     */
    public function testComplete(string $originStr, string $onlineStr, string $expected)
    {
        $origin = WikiTemplateFactory::create('ouvrage');
        $origin->hydrateFromText($originStr);

        $online = WikiTemplateFactory::create('ouvrage');
        $online->hydrateFromText($onlineStr);

        $comp = new OuvrageComplete($origin, $online);
        $this::assertEquals(
            $expected,
            $comp->getResult()->serialize(true)
        );
    }

    public function provideComplete()
    {
        return [
            [
                // Wikidata+BnF completion of sitelinks
                '{{Ouvrage|prénom1=Paul|nom1=Durand|titre=La vie}}',
                '{{Ouvrage|prénom1=Paul|nom1=Durand|lien auteur1=Paul Durand (écrivain)|titre=La Vie|lien titre=La Vie (livre)}}',
                '{{Ouvrage|prénom1=Paul|nom1=Durand|lien auteur1=Paul Durand (écrivain)|titre=La vie|lien titre=La Vie (livre)|éditeur=|année=|pages totales=|isbn=}}',
            ],
            [
                // Wikidata+BnF completion of sitelinks : prénoms différents
                '{{Ouvrage|prénom1=Paul A.|nom1=Durand|titre=La vie|isbn=1234}}',
                '{{Ouvrage|prénom1=Paul-André|nom1=Durand|lien auteur1=Paul Durand (écrivain)|titre=La Vie|isbn=1234}}',
                '{{Ouvrage|prénom1=Paul A.|nom1=Durand|lien auteur1=Paul Durand (écrivain)|titre=La vie|éditeur=|année=|pages totales=|isbn=1234}}',
            ],
            [
                // Google partiel
                '{{Ouvrage|titre=}}',
                '{{Ouvrage|titre=|présentation en ligne=https://books.google.com/books?id=day56Sz-rEEC}}',
                '{{Ouvrage|titre=|éditeur=|année=|pages totales=|isbn=|lire en ligne=https://books.google.com/books?id=day56Sz-rEEC}}',
            ],
            [
                // Google total
                '{{Ouvrage|titre=}}',
                '{{Ouvrage|titre=|lire en ligne=https://books.google.com/books?id=day56Sz-rEEC}}',
                '{{Ouvrage|titre=|éditeur=|année=|pages totales=|isbn=|lire en ligne=https://books.google.com/books?id=day56Sz-rEEC}}',
            ],
            [
                //isbn invalide
                '{{Ouvrage|titre=}}',
                '{{Ouvrage|titre=|isbn invalide=bla}}',
                '{{Ouvrage|titre=|éditeur=|année=|pages totales=|isbn=}}',
            ],
            // date/année
            [
                '{{Ouvrage|titre=}}',
                '{{Ouvrage|titre=|année=2009}}',
                '{{Ouvrage|titre=|éditeur=|année=2009|pages totales=|isbn=}}',
            ],
            [
                '{{Ouvrage|titre=|date=2011}}',
                '{{Ouvrage|titre=|année=2009}}',
                '{{Ouvrage|titre=|éditeur=|date=2011|pages totales=|isbn=}}',
            ],
            /*
             * titre + sous-titre
             */ // pas d'ajout si déjà titre volume/chapitre/tome ou nature ouvrage
            [
                '{{Ouvrage|titre = Loiret Joli|titre chapitre=Bla}}',
                '{{Ouvrage|titre = Loiret Joli|sous-titre=un département}}',
                '{{Ouvrage|titre=Loiret Joli|éditeur=|année=|pages totales=|isbn=|titre chapitre=Bla}}',
            ],
            // titres identiques mais sous-titre manquant
            [
                '{{Ouvrage|titre = Loiret Joli}}',
                '{{Ouvrage|titre = Loiret Joli|sous-titre=un département}}',
                '{{Ouvrage|titre=Loiret Joli|sous-titre=un département|éditeur=|année=|pages totales=|isbn=}}',
            ],
            // punctuation titre différente, sous-titre manquant
            [
                '{{Ouvrage|titre = Loiret Joli !!!!}}',
                '{{Ouvrage|titre = Loiret Joli|sous-titre=un département}}',
                '{{Ouvrage|titre=Loiret Joli !!!!|sous-titre=un département|éditeur=|année=|pages totales=|isbn=}}',
            ],
            // sous-titre inclus dans titre original
            [
                '{{Ouvrage|titre = Loiret Joli : un département}}',
                '{{Ouvrage|titre = Loiret Joli|sous-titre=un département}}',
                '{{Ouvrage|titre=Loiret Joli|sous-titre=un département|éditeur=|année=|pages totales=|isbn=}}',
            ],
            // sous-titre absent online
            [
                '{{Ouvrage|titre = Loiret Joli|sous-titre=un département}}',
                '{{Ouvrage|titre = Loiret Joli}}',
                '{{Ouvrage|titre=Loiret Joli|sous-titre=un département|éditeur=|année=|pages totales=|isbn=}}',
            ],
            // titre absent online
            [
                '{{Ouvrage|auteur1=bla|titre = Loiret Joli}}',
                '{{Ouvrage|auteur1=bla}}',
                '{{Ouvrage|auteur1=bla|titre=Loiret Joli|éditeur=|année=|pages totales=|isbn=}}',
            ],
            // titre volume existe -> skip
            [
                '{{Ouvrage|titre = Loiret Joli|titre volume=Bla}}',
                '{{Ouvrage|titre = Loiret Joli|sous-titre=Fubar}}',
                '{{Ouvrage|titre=Loiret Joli|titre volume=Bla|éditeur=|année=|pages totales=|isbn=}}',
            ],
            [
                '{{Ouvrage|titre = Loiret Joli|collection=Bla}}',
                '{{Ouvrage|titre = Loiret Joli|sous-titre=Fubar}}',
                '{{Ouvrage|titre=Loiret Joli|éditeur=|collection=Bla|année=|pages totales=|isbn=}}',
            ],
        ];
    }

    /**
     * @dataProvider provideAuthors
     *
     * @throws Exception
     */
    public function testSameAuthors($originStr, $onlineStr, bool $same)
    {
        $origin = WikiTemplateFactory::create('ouvrage');
        $origin->hydrateFromText($originStr);

        $online = new OuvrageClean();
        $online->hydrateFromText($onlineStr);

        $comp = new OuvrageComplete($origin, $online);
        $this::assertEquals(
            $same,
            $comp->hasSameAuthors()
        );
    }

    public function provideAuthors(): array
    {
        return [
            [
                '{{Ouvrage|auteurs=Bob Martin|titre =Bla}}',
                '{{Ouvrage|prénom1=Bob|nom1=Martin|titre =Bla}}',
                true,
            ],
            [
                '{{Ouvrage|auteurs=Bob Martin|titre =Bla}}',
                '{{Ouvrage|prénom1=TATA|nom1=Martin|titre =Bla}}',
                false,
            ],
        ];
    }
}
