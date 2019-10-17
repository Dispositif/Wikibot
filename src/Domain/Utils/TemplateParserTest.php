<?php

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

    public function provideStyleSeparator()
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
        ];
    }

    public function testFindAllTemplatesByName()
    {
        $this::markTestIncomplete('not implemented');
    }

    /**
     * @dataProvider provideParseDataFromTemplate
     * @param       $template
     * @param       $text
     * @param array $expected
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
    public function provideParseDataFromTemplate()
    {
        return [
            //            [
            //                'ouvrage',
            //                '{{ouvrage|titre = Dictionnaire bibliographique russe/[Русский биографический словарь]|partie = Terebeniov /{bla} }}',
            //                [
            //                    'titre' => 'Dictionnaire bibliographique russe/[Русский биографический словарь]',
            //                    'partie' => 'Terebeniov /{bla}',
            //                ],
            //            ],
            // erreur : {bla} sur autre paramètre
            //            ['ouvrage', '{{ouvrage|title=blaческiй|nom=po{{nobr|Alexandre {{VI}}}}}}', ['title' => 'bla','nom'=>'po']],
            ['ouvrage', '{{ ouvrage | title =bla | nom = po }}', ['title' => 'bla', 'nom' => 'po']],
            // ok
            ['ouvrage', '{{ouvrage|bla|po}}', ['1' => 'bla', '2' => 'po']],
            // ok
        ];
    }

    public function testParseAllTemplateByName()
    {
        $this::markTestIncomplete('not implemented');
    }
}
