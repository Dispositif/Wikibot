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

    /**
     * @dataProvider providePageTemplate
     */
    public function testExtractPageTemplateContent(string $text, $expected)
    {
        $this::assertSame(
            $expected,
            TemplateParser::extractPageTemplateContent($text)
        );
    }

    public static function providePageTemplate(): array
    {
        return [
            ['bla', null],
            ['bla {{P.}}250 bla', ['{{P.}}250', '250']],
            ['bla {{p.|125-133}} bla', ['{{p.|125-133}}', '125-133']],
            ['bla {{p.}}125-133 bla', ['{{p.}}125-133', '125-133']],
            ['bla {{p.}}10, 20, 35-36 bla', ['{{p.}}10, 20, 35-36', '10, 20, 35-36']],
            ['{{p.|125-133}} bla {{p.|55}}', ['{{p.|125-133}}', '125-133']],
        ];
    }
}
