<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Publisher\Tests;

use App\Domain\Publisher\SeoSanitizer;
use PHPUnit\Framework\TestCase;

class SeoSanitizerTest extends TestCase
{
    private $sanitizer;

    public function setUp(): void
    {
        $this->sanitizer = new SeoSanitizer();
    }

    /**
     * @dataProvider provideTitleResult
     */
    public function testCleanSEOTitle(?string $prettyDomain, ?string $title, ?string $expected)
    {
        $this::assertSame($expected, $this->sanitizer->cleanSEOTitle($prettyDomain, $title));
    }

    public function provideTitleResult(): array
    {
        return [
            ['test.com', '', null], // no title TODO ?
            ['fu-bar.com', 'First | fu-bar.com', 'First'],
            ['fu-bar.com', 'First | fu-bar.com | Second', 'First - Second'],
            ['fu-bar.com', 'First is a very long phrase which could be enough | fu-bar.com | Second', 'First is a very long phrase which could be enough'],
            ['fu.com', 'Le Rouget de Lisle - Pâtisserie Rouget de Lisle / Lons-le-Saunier', 'Le Rouget de Lisle - Pâtisserie Rouget de Lisle'],

            ['fu-bar.com', 'Second | fu-bar.com', 'Second'], //strip SEO
            ['fu-bar.com', 'Second | pif | paf', 'Second - pif'], // only 2 SEO segments
            ['fu-bar.com', 'Second | fu-bar', 'Second'], // strip SEO without last domain
            ['fu-bar.com', 'Second | fu bar', 'Second'], //strip SEO
            ['fu-bar.com', 'Second | fu bar the best site', 'Second'], //strip SEO
            ['fu-bar.com', 'fu bar the best site | Second', 'Second'], //strip SEO
            ['fu-bar.com', 'fu bar the best site', 'fu bar the best site'], //keep 1 segment

            ['fu-bar.com', 'Second - fu-bar.com', 'Second'], // SEO separator -
            ['fu-bar.com', 'Second – fu-bar.com', 'Second'], // SEO separator –
            ['fu-bar.com', 'Second / fu-bar.com', 'Second'], // SEO separator /

            //  http://www.alternantesfm.net/emissions/emissions-speciales/marie-josee-christien-presente-son-dernier-recueil-de-poesie/
            ['alternantesfm.net', "Marie-Josée Christien présente son dernier recueil de poésie. - Radio AlterNantes FM", 'Marie-Josée Christien présente son dernier recueil de poésie.'],
            // titre=Planète Jeunesse - Tom et Jerry (1940-1958)<!-- Vérifiez ce titre --> |url=http://www.planete-jeunesse.com/fiche-725-tom-et-jerry.html |site=planete-jeunesse.com
            ['planete-jeunesse.com', 'Planète Jeunesse - Tom et Jerry (1940-1958)', 'Tom et Jerry (1940-1958)'],
//            ['archives-org.com',  "ARIA Australian Top 50 Albums / Australia's Official Top 50 Albums - ARIA Charts", "ARIA Australian Top 50 Albums / Australia's Official Top 50 Albums"], // mixed seo separators
            ['college-de-france.fr', "Collège de France - Enseigner la recherche en train de se faire", 'Enseigner la recherche en train de se faire'],
            ['madridimagen.com', "Madridimagen.com - Ce site web est à vendre ! - Ressources et information concernant madridimagen Resources and Information.", 'Ce site web est à vendre !'],
            // France Biotech - Les entrepreneurs de la HealthTech |url=http://www.france-biotech.fr
            ['france-biotech.fr', 'France Biotech - Les entrepreneurs de la HealthTech', 'Les entrepreneurs de la HealthTech'],
        ];
    }
}