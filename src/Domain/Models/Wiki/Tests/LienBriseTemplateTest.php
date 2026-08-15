<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Models\Wiki\Tests;

use App\Domain\Models\Wiki\LienBriseTemplate;
use PHPUnit\Framework\TestCase;

class LienBriseTemplateTest extends TestCase
{
    public function testInheritsBibliographicParamsFromLienWeb()
    {
        // "date"/"site"/"éditeur"/"auteur1" : hérités de LienWebTemplate, pas dans l'ancienne
        // liste à 7 paramètres. Un contributeur les garde souvent en convertissant {{lien web}}
        // en {{lien brisé}} ; avant l'héritage, ils étaient marqués PARAMETRE N'EXISTE PAS.
        $lienBrise = new LienBriseTemplate();
        $lienBrise->hydrateFromText(
            '{{Lien brisé |url=http://example.org |titre=Bla |auteur1=Durand |date=2015 |site=Le Monde |éditeur=Le Monde |brisé le=2020}}'
        );

        $this::assertSame('Durand', $lienBrise->getParam('auteur1'));
        $this::assertSame('2015', $lienBrise->getParam('date'));
        $this::assertSame('Le Monde', $lienBrise->getParam('site'));
        $this::assertSame('Le Monde', $lienBrise->getParam('éditeur'));
        $this::assertEmpty($lienBrise->parametersErrorFromHydrate);
    }

    public function testAdresseAliasSpecificToLienBrise()
    {
        $lienBrise = new LienBriseTemplate();
        $lienBrise->hydrateFromText('{{Lien brisé |adresse=http://example.org |titre=Bla |brisé le=2020}}');

        $this::assertSame('http://example.org', $lienBrise->getParam('url'));
        // "adresse" est un alias (résolu par getParam), pas une clé stockée séparément
        $this::assertArrayNotHasKey('adresse', $lienBrise->toArray());
    }

    public function testSharedAliasFromLienWebAlsoWorks()
    {
        // "lire en ligne" -> alias partagé via LienWebTemplate::PARAM_ALIAS
        $lienBrise = new LienBriseTemplate();
        $lienBrise->hydrateFromText('{{Lien brisé |lire en ligne=http://example.org |titre=Bla |brisé le=2020}}');

        $this::assertSame('http://example.org', $lienBrise->getParam('url'));
    }

    public function testOnlyUrlIsRequired()
    {
        $this::assertSame(['url'], LienBriseTemplate::REQUIRED_PARAMETERS);
    }

    public function testWikitemplateName()
    {
        $this::assertSame('Lien brisé', LienBriseTemplate::WIKITEMPLATE_NAME);
    }
}
