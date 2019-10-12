<?php

declare(strict_types=1);

namespace App\Domain\Utils;

use PHPUnit\Framework\TestCase;

class WikiTextUtilTest extends TestCase
{

    public function testRemoveHTMLcomments()
    {
        $text = 'blabla<!-- sdfqfqs 
<!-- blbal 
    --> ez';
        $this::assertSame(
            'blabla ez',
            WikiTextUtil::removeHTMLcomments($text)
        );
    }

    public function testFindUserStyleSeparator()
    {
        $this::markTestIncomplete('not implemented');
    }

    public function testIsCommented()
    {
        $text = 'blabla<!-- sdfqfqs 
        --> ez';
        $this::assertSame(
            true,
            WikiTextUtil::isCommented($text)
        );

        $this::assertSame(
            false,
            WikiTextUtil::isCommented('bla')
        );
    }

    /**
     * @dataProvider provideWikify
     */
    public function testUnWikify(string $text, string $expected)
    {
        $this::assertEquals(
            $expected,
            WikiTextUtil::unWikify($text)
        );
    }

    public function provideWikify()
    {
        return [
            ['blabla<!-- fu -->', 'blabla'],
            ['{{lang|en|fubar}}', 'fubar'],
            ['{{langue|en|fubar}}', 'fubar'],
            ['[[wikilien]', 'wikilien'],
            ['[[wiki|wikilien]]', 'wikilien'],
            ['{{en}}', '{{en}}'],
        ];
    }

    public function testFindAllTemplatesByName()
    {
        $this::markTestIncomplete('not implemented');
    }

    /**
     * @dataProvider provideParseDataFromTemplate
     */
    public function testParseDataFromTemplate($template, $text, array $expected)
    {
        $this::assertEquals(
            $expected,
            WikiTextUtil::parseDataFromTemplate($template, $text)
        );
    }

    /**
     * TODO {{nobr|Alexandre {{IV}}}}
     * todo \n
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
