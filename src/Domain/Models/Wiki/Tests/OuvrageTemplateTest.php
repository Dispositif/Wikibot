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
        $this::assertSame(
            "{{Ouvrage |titre=bla |volume=5 |éditeur= |année= |pages totales= |isbn= |volume-doublon=3 <!--PARAMETRE 'volume-doublon' N'EXISTE PAS -->}}",
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

    public function provideSpanInitial()
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
