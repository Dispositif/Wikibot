<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Publisher\Tests;

use App\Domain\Publisher\OpenGraphMapper;
use PHPUnit\Framework\TestCase;

class OpenGraphMapperTest extends TestCase
{
    /**
     * @var OpenGraphMapper
     */
    private $mapper;

    public function setUp(): void
    {
        $this->mapper = new OpenGraphMapper();
    }

    /**
     * @dataProvider provideBestTitle
     */
    public function testChooseBestTitle(?string $metaTitle, ?string $htmlTitle, ?string $metaH1, ?string $expected)
    {
        $actual = $this->mapper->chooseBestTitle($metaTitle, $htmlTitle, $metaH1);
        $this::assertSame($expected, $actual);
    }

    public function provideBestTitle(): array
    {
        return [
            ['', '', '', ''], // no title TODO ?
            ['First', '', '', 'First'],
            ['First', 'Second', 'Third', 'First'], // meta data prevals
            ['', 'Second', 'Third', 'Second'], // use html-title
            ['', 'Second / Tic / Tac', 'Second', 'Second'], // H1 included in title => H1 only
        ];
    }
}
