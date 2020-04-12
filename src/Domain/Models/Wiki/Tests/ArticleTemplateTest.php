<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Models\Wiki\Tests;

use App\Domain\Models\Wiki\ArticleTemplate;
use App\Domain\Models\Wiki\TemplateConverter;
use App\Domain\WikiTemplateFactory;
use PHPUnit\Framework\TestCase;

class ArticleTemplateTest extends TestCase
{
    /**
     * @dataProvider provideArticleSerialize
     */
    public function testArticleSerialize(array $data, string $serial)
    {
        $art = new ArticleTemplate();
        $art->hydrate($data);
        $this::assertSame(
            $serial,
            $art->serialize(true)
        );
    }

    public function provideArticleSerialize(): array
    {
        return [
            [
                ['titre' => 'bla'],
                '{{Article |auteur1= |titre=bla |périodique= |date= |lire en ligne=}}',
            ],
            [
                // {{article | langue = en | auteur1 = T. Breeze | auteur2 = A. Bailey | auteur3 = K. Balcombe | auteur4 = S. Potts | titre = Pollination services in the UK: How important are honeybees? | journal = Agriculture, Ecosystems and Environment | date = août 2011 | doi = 10.1016/j.agee.2011.03.020 }}
                [
                    'langue' => 'en',
                    'auteur1' => 'T. Breeze',
                    'auteur2' => 'A. Bailey',
                    'auteur3' => 'K. Balcombe',
                    'auteur4' => 'S. Potts',
                    'titre' => 'Pollination services in the UK: How important are honeybees?',
                    'journal' => 'Agriculture, Ecosystems and Environment',
                    'numéro' => '13',
                    'date' => 'août 2011',
                    'doi' => '10.1016/j.agee.2011.03.020',
                ],
                '{{Article |langue=en |auteur1=T. Breeze |auteur2=A. Bailey |auteur3=K. Balcombe |auteur4=S. Potts |titre=Pollination services in the UK: How important are honeybees? |périodique=Agriculture, Ecosystems and Environment |numéro=13 |date=août 2011 |lire en ligne= |doi=10.1016/j.agee.2011.03.020}}',
            ],
        ];
    }

    /**
     * @dataProvider provideConvertOuvrage2Article
     */
    public function testConvertFromOuvrage(string $ouvrageSerial, string $articleSerial)
    {
        $ouvrage = WikiTemplateFactory::create('ouvrage');
        $ouvrage->hydrateFromText($ouvrageSerial);

        $article = TemplateConverter::ArticleFromOuvrage($ouvrage);
        $this::assertSame(
            $articleSerial,
            $article->serialize(true)
        );
    }

    public final function provideConvertOuvrage2Article(): array
    {
        return [
            [
                '{{Ouvrage|langue=en|auteur=Barry Walfish|lire en ligne=https://www.jstor.org/stable/1453892|titre=The Two Commentaries of Abraham Ibn Ezra on the Book of Esther|périodique=The Jewish Quarterly Review, New Series|tome=79|numéro=4|mois=avril|année=1989|passage=323-343|éditeur=University of Pennsylvania Press}}',
                "{{Article |langue=en |auteur1=Barry Walfish |titre=The Two Commentaries of Abraham Ibn Ezra on the Book of Esther |périodique=The Jewish Quarterly Review, New Series |éditeur=University of Pennsylvania Press |volume=79 |numéro=4 |date=avril 1989 |pages=323-343 |lire en ligne=https://www.jstor.org/stable/1453892}}",
            ],
            [
                "{{Ouvrage|langue=en|auteur=Hans Vlieghe|titre=Flemish Art and Architecture, 1585-1700|périodique=The Burlington Magazine|lieu=New Haven|éditeur=Yale University Press|année=1998|lire en ligne={{Google Livres|AS_NXFoY0M4C}}|isbn=978-0-30010-469-1|pages totales=339}}",
                "{{Article |langue=en |auteur1=Hans Vlieghe |titre=Flemish Art and Architecture, 1585-1700 |périodique=The Burlington Magazine |lieu=New Haven |éditeur=Yale University Press |date=1998 |pages=339 |isbn=978-0-30010-469-1 |lire en ligne={{Google Livres|AS_NXFoY0M4C}}}}",
            ],
            [
                "{{Ouvrage|nom1=Collectif|prénom2=Alphonse|nom2= Wollbrett|directeur2=oui|titre=Le canton de Bouxwiller|lieu= Saverne|éditeur=SHASE|année=1978|issn=0245-8411|périodique=Pays d'Alsace|numéro=103bis}}",
                "{{Article |auteur1= |nom1=Collectif |prénom2=Alphonse |nom2=Wollbrett |directeur2=oui |titre=Le canton de Bouxwiller |périodique=Pays d'Alsace |lieu=Saverne |éditeur=SHASE |numéro=103bis |date=1978 |issn=0245-8411 |lire en ligne=}}",
            ],
        ];
    }
}
