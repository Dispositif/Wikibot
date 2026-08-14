<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Models\Wiki\Tests;

use App\Domain\Models\Wiki\LienWebTemplate;
use PHPUnit\Framework\TestCase;

class LienWebTemplateTest extends TestCase
{
    private function hydrate(string $wikitext): LienWebTemplate
    {
        $lienWeb = new LienWebTemplate();
        $lienWeb->hydrateFromText($wikitext);

        return $lienWeb;
    }

    /**
     * @dataProvider provideAliases
     */
    public function testAliasResolution(string $rawParam, string $canonicalParam)
    {
        $lienWeb = $this->hydrate(
            sprintf('{{lien web |titre=Bla |%s=valeur}}', $rawParam)
        );

        $this::assertSame('valeur', $lienWeb->getParam($canonicalParam));
    }

    public static function provideAliases(): array
    {
        return [
            'lire en ligne -> url' => ['lire en ligne', 'url'],
            'url texte -> url' => ['url texte', 'url'],
            'website -> site' => ['website', 'site'],
            'publisher -> éditeur' => ['publisher', 'éditeur'],
            'trad -> traducteur' => ['trad', 'traducteur'],
            'Consulté le -> consulté le' => ['Consulté le', 'consulté le'],
            'consultée le -> consulté le' => ['consultée le', 'consulté le'],
            'access date -> consulté le' => ['access date', 'consulté le'],
            'co-auteur -> coauteurs' => ['co-auteur', 'coauteurs'],
            'coauthors -> coauteurs' => ['coauthors', 'coauteurs'],
            'extrait -> citation' => ['extrait', 'citation'],
            'quote -> citation' => ['quote', 'citation'],
            'pmc -> pmcid' => ['pmc', 'pmcid'],
            'url-access -> accès url' => ['url-access', 'accès url'],
            'zbmath -> zbl' => ['zbmath', 'zbl'],
            // Déprécié mais toujours rendu par le wikifier MediaWiki (single-author,
            // avant la convention numérotée) -- régression trouvée 2026-08-14, voir
            // ExistingRefTransformer.
            'prénom -> prénom1' => ['prénom', 'prénom1'],
            'nom -> nom1' => ['nom', 'nom1'],
        ];
    }

    /**
     * @dataProvider provideRecognizedParams
     */
    public function testParamsAddedIn202608AreRecognized(string $param)
    {
        // avant l'audit 2026-08, ces paramètres officiels (TemplateData Modèle:Lien web)
        // n'étaient pas reconnus et se retrouvaient tagués <!--PARAMETRE N'EXISTE PAS-->
        $lienWeb = $this->hydrate(
            sprintf('{{lien web |titre=Bla |%s=valeur}}', $param)
        );

        $this::assertSame('valeur', $lienWeb->getParam($param));
        $this::assertEmpty($lienWeb->parametersErrorFromHydrate);
    }

    public static function provideRecognizedParams(): array
    {
        return [
            ['auteurs'],
            ['wikisource'],
            ['hdl'],
            ['accès hdl'],
            ['pmcid'],
            ['s2cid'],
            ['libris'],
            ['citeseerx'],
            ['préface'],
            ['postface'],
            ['illustrateur'],
            ['langue originale'],
        ];
    }

    public function testDoublonParamIsFlaggedNotSilentlyOverwritten()
    {
        $lienWeb = $this->hydrate(
            '{{lien web |titre=Bla |url=http://example.org |lire en ligne=http://autre.org}}'
        );

        $this::assertSame('http://example.org', $lienWeb->getParam('url'));
        $this::assertArrayHasKey('url-doublon', $lienWeb->parametersErrorFromHydrate);
        $this::assertSame('http://autre.org', $lienWeb->parametersErrorFromHydrate['url-doublon']);
    }

    public function testSetTitreCapitalizesFirstLetter()
    {
        $lienWeb = $this->hydrate('{{lien web |titre=bla bla |url=http://example.org}}');

        $this::assertSame('Bla bla', $lienWeb->getParam('titre'));
    }

    public function testSetTitreNormalizesColonSpacing()
    {
        $lienWeb = $this->hydrate('{{lien web |titre=Bla:bla |url=http://example.org}}');

        $this::assertSame('Bla : bla', $lienWeb->getParam('titre'));
    }

    public function testUnknownParamIsFlaggedAsError()
    {
        $lienWeb = $this->hydrate('{{lien web |titre=Bla |url=http://example.org |paramInexistant=valeur}}');

        // TemplateParser normalise tous les noms de paramètre en minuscules avant résolution d'alias
        $this::assertArrayHasKey('paraminexistant', $lienWeb->parametersErrorFromHydrate);
    }
}
