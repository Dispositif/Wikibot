<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Publisher\Tests;

use App\Domain\Publisher\Traits\MapperConverterTrait;
use PHPUnit\Framework\TestCase;

class MapperConverterTraitTest extends TestCase
{
    use MapperConverterTrait;

    /**
     * @dataProvider provideCleanData
     */
    public function testClean($text, $expected): void
    {
        $this::assertSame(
            $expected,
            $this->clean($text)
        );
    }

    public function provideCleanData(): array
    {
        return [
            ['bla|bla bob@gmail.com', 'bla/bla'],
            [
                'author of <span style="color : orange;"><i>Ancillary Justice</i></span> (Imperial Radch 1 )',
                'author of Ancillary Justice (Imperial Radch 1 )',
            ],
            ['{{bla}}','bla']
        ];
    }

}
