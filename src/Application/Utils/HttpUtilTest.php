<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\Utils;

use PHPUnit\Framework\TestCase;

class HttpUtilTest extends TestCase
{
    public static function provideUrl(): array
    {
        return [
            ['https://fr.wikipedia.fr/wiki/WP:BOT', true],

        ];
    }

    /**
     * @dataProvider provideUrl
     */
    public function testIsWebUrl(string $url, bool $expected)
    {
        $this::assertSame($expected, HttpUtil::isHttpURL($url));
    }
}
