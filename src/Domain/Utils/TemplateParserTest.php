<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Utils;

use PHPUnit\Framework\TestCase;

class TemplateParserTest extends TestCase
{
    /**
     * @dataProvider provideStyleSeparator
     *
     * @param $text
     * @param $expected
     */
    public function testFindUserStyleSeparator($text, $expected)
    {
        $this::assertSame(
            $expected,
            TemplateParser::findUserStyleSeparator($text)
        );
    }

    public static function provideStyleSeparator()
    {
        return [
            ['{{Ouvrage|langue=fr|prénom1=Ernest|nom1=Nègre|titre=Toponymie}}', '|'],
            ['{{Ouvrage |langue=fr |prénom1=Ernest |nom1=Nègre |titre=Toponymie }}', ' |'],
            ['{{Ouvrage | langue=fr | prénom1=Ernest | nom1=Nègre | titre=Toponymie }}', ' | '],
            [
                '{{Ouvrage
|langue=fr
|prénom1=Ernest
|nom1=Nègre
|titre=Toponymie
}}',
                "\n|",
            ],
            ['{{Ouvrage
  |langue=fr
  |prénom1=Ernest
  |nom1=Nègre
  |titre=Toponymie
}}',
                "\n |",
            ],
            ['{{Ouvrage
  | langue=fr
}}',
             "\n | ",
            ],
        ];
    }

    public function testFindAllTemplatesByName(): never
    {
        $this::markTestIncomplete('not implemented');
    }

    /**
     * @dataProvider provideParseDataFromTemplate
     *
     * @param       $template
     * @param       $text
     */
    public function testParseDataFromTemplate($template, $text, array $expected)
    {
        $this::assertEquals(
            $expected,
            TemplateParser::parseDataFromTemplate($template, $text)
        );
    }

    /**
     * TODO {{nobr|Alexandre {{IV}}}}
     * todo \n.
     *
     * @return array
     */
    public static function provideParseDataFromTemplate()
    {
        return [
            ['ouvrage', '{{ ouvrage | title =bla | nom = po }}', ['title' => 'bla', 'nom' => 'po']],
            ['ouvrage', '{{ouvrage|bla|po}}', ['1' => 'bla', '2' => 'po']],
        ];
    }

    public function testParseAllTemplateByName(): never
    {
        $this::markTestIncomplete('not implemented');
    }
}
