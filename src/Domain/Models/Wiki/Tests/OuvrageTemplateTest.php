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
