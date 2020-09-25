<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Publisher\Tests;

use App\Domain\Publisher\ExternConverterTrait;
use PHPUnit\Framework\TestCase;

class ExternConverterTest extends TestCase
{
    use ExternConverterTrait;

    /**
     * @dataProvider provideCleanData
     */
    public function testClean($text, $expected)
    {
        $this::assertSame(
            $expected,
            $this->clean($text)
        );
    }

    public function provideCleanData()
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
