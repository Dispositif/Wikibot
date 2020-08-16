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

    public function testClean()
    {
        $string = 'bla|bla bob@gmail.com';
        $this::assertSame(
            'bla/bla',
            $this->clean($string));
    }

}
