<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Utils;

use PHPUnit\Framework\TestCase;

class WikiTextUtilTest extends TestCase
{
    public static function provideExternalLink(): array
    {
        return [
            ['[[fu]] [http://google.fr bla] [http://google.com blo]', '[[fu]] bla blo'],
            ['bla [http://google.fr]', 'bla'],
        ];
    }

    public static function provideWikilink()
    {
        return [
            [['fu_bar'], '[[fu bar]]'],
            [['fu', 'Fu'], '[[fu]]'],
            [['fu', 'bar'], '[[Bar|fu]]'],
            [['fu', '[[Bar]]'], '[[Bar|fu]]'], // Erreur "|lien auteur=[[Bla]]"
        ];
    }

    public static function provideWikify()
    {
        return [
            ['blabla<!-- fu -->', 'blabla'],
            ['{{lang|en|fubar}}', 'fubar'],
            ['{{langue|en|fubar}}', 'fubar'],
            ['[[wikilien]', 'wikilien'],
            ['[[wiki|wikilien]]', 'wikilien'],
            ['{{en}}', '{{en}}'],
            ['{{Lien|Jeffrey Robinson}}', 'Jeffrey Robinson'],
        ];
    }

    /**
     * @dataProvider provideConcatenatedRefFixture
     */
    public function testFixConcatenatedRefs($text, $expected)
    {
        $this::assertSame($expected, WikiTextUtil::fixConcatenatedRefsSyntax($text));
    }

    public static function provideConcatenatedRefFixture(): array
    {
        return [
            ['<ref>fu</ref><ref name="1">bar</ref>', '<ref>fu</ref>{{,}}<ref name="1">bar</ref>'],
            ['<ref>fu</ref>  <ref name="1">bar</ref>', '<ref>fu</ref>{{,}}<ref name="1">bar</ref>'],
            ['<ref>fu</ref>{{,}}<ref name="1">bar</ref>', '<ref>fu</ref>{{,}}<ref name="1">bar</ref>'],
            ['<ref name="A" /> <ref name="B">', '<ref name="A" />{{,}}<ref name="B">'],
            ['<ref name=A /><ref name="B">', '<ref name=A />{{,}}<ref name="B">'],
        ];
    }

    /**
     * @dataProvider provideExternalLink
     */
    public function testStripExternalLink($text, $expected)
    {
        $this::assertSame(
            $expected,
            WikiTextUtil::stripExternalLink($text)
        );
    }

    public function testExtractAllRefs()
    {
        $text = <<<EOF
bla <ref>toto.</ref> bla <ref name="tutu">Plop</ref>.

* [[bob]]
* https://test.com/page.html
*https://example.com/papa.

EOF;
        $expected = [
            0 => ['<ref>toto.</ref>', 'toto.'],
            1 => ['<ref name="tutu">Plop</ref>', 'Plop'],
            2 => [
                "* https://test.com/page.html\n",
                'https://test.com/page.html',
            ],
            3 => [
                "*https://example.com/papa.\n",
                'https://example.com/papa',
            ],
        ];

        $this::assertSame(
            $expected,
            WikiTextUtil::extractRefsAndListOfLinks($text)
        );
    }

    /**
     * @dataProvider provideWikilink
     */
    public function testWikilink($data, $expected)
    {
        $this::assertSame(
            $expected,
            WikiTextUtil::wikilink($data[0], $data[1] ?? null)
        );
    }

    public function testUpperfirst()
    {
        $this::assertSame(
            'Économie',
            WikiTextUtil::mb_ucfirst('économie')
        );
    }

    public function testLowerfirst()
    {
        $this::assertSame(
            'économie',
            WikiTextUtil::mb_lowerfirst('Économie')
        );
    }

    public function testGetWikilinkPages()
    {
        $text = 'bla [[fu|bar]] et [[back]] mais pas [[wikt:toto|bou]]';

        $this::assertSame(
            ['fu', 'back'],
            WikiTextUtil::getWikilinkPages($text)
        );
    }

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
}
