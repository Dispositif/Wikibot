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

class InfoTraitTest extends TestCase
{
    /**
     * @throws \Exception
     */
    public function testInfoTrait()
    {
        $ouvrage = \App\Domain\WikiTemplateFactory::create('ouvrage');
        $ouvrage->hydrate(
            [
                'auteur' => 'Michou',
                'titre' => 'Au soleil',
            ]
        );

        $ouvrage->setInfos(['test' => 'bla']);
        $this::assertSame(
            ['test' => 'bla'],
            $ouvrage->getInfos()
        );

        $ouvrage->setInfo('toto', 'bla2');
        $this::assertSame(
            'bla2',
            $ouvrage->getInfo('toto')
        );
    }
}
