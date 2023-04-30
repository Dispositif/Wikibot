<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Tests;

use App\Domain\Utils\NumberUtil;
use PHPUnit\Framework\TestCase;

class NumberUtilTest extends TestCase
{
    /**
     * @dataProvider provideArab2roman
     *
     * @param string $expected
     */
    public function testArab2roman(int $number, ?string $expected)
    {
        $this::assertSame(
            $expected,
            NumberUtil::arab2roman($number)
        );
    }

    public static function provideArab2roman(): array
    {
        return [
            [24, 'XXIV'],
            [-12, null],
        ];
    }

    public function testArab2romanLowerSize()
    {
        $this::assertSame(
            'xxiv',
            NumberUtil::arab2roman(24, true)
        );
    }
}
