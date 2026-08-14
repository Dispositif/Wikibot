<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Models\Wiki\Tests;

use App\Domain\Models\Wiki\OuvrageTemplate;
use PHPUnit\Framework\TestCase;

class OuvrageTemplateTest extends TestCase
{
    public function testDoublonAlias()
    {
        $ouvrage = new OuvrageTemplate();
        $ouvrage->hydrateFromText(
            "{{Ouvrage |titre=bla |volume=5 |vol=3}}"
        );
        // "N'EXISTE PAS" comment annotation temporarily disabled (2026-08-14, see
        // AbstractWikiTemplate::ANNOTATE_UNRECOGNIZED_PARAMS)
        $this::assertSame(
            "{{Ouvrage |titre=bla |volume=5 |éditeur= |année= |isbn= |volume-doublon=3}}",
            $ouvrage->serialize(true)
        );
    }

    /**
     * @dataProvider provideSpanInitial
     */
    public function testSpanInitial(array $data, ?string $expected)
    {
        $ouvrage = new OuvrageTemplate();
        $ouvrage->hydrate($data);

        $this::assertSame(
            $expected,
            $ouvrage->getSpanInitial()
        );
    }

    public static function provideSpanInitial()
    {
        return [
            [
                ['id' => 'Bla'],
                'Bla',
            ],
            [
                ['auteur' => 'Dupont', 'année' => '1989'],
                'Dupont1989',
            ],
            [
                ['auteur1' => 'Dupont', 'auteur2' => 'Durand', 'année' => '1989'],
                'DupontDurand1989',
            ],
        ];
    }
}
