<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Enums;

use PHPUnit\Framework\TestCase;

class LanguageTest extends TestCase
{
    /**
     * @dataProvider provideAll2wiki
     */
    public function testAll2wiki($langStr, $expected)
    {
        $this::assertSame(
            $expected,
            Language::all2wiki($langStr)
        );
    }

    public function provideAll2wiki(): array
    {
        return [
            ['en-us', 'en'],
            ['fr-fr', 'fr'],
            ['fr', 'fr'],
            ['FR', 'fr'],
            ['fre', 'fr'],
            // iso
            ['dan', 'da'],
            ['DAN', 'da'],
            ['aav', null],
            ['coréen', 'ko'],
            ['anglais', 'en'],
            ['ANGLAIS', 'en'],
            ['English', 'en'],
            ['english', 'en'],
            ['ENGLISH', 'en'],
            ['Afrikaans', 'af'],
            ['turc', 'tr']
        ];
    }
}
