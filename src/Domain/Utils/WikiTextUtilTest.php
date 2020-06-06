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

    public function provideExternalLink(): array
    {
        return [
            ['[[fu]] [http://google.fr bla] [http://google.com blo]', '[[fu]] bla blo'],
            ['bla [http://google.fr]', 'bla'],
        ];
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

    public function provideWikilink()
    {
        return [
            [['fu_bar'], '[[fu bar]]'],
            [['fu', 'Fu'], '[[fu]]'],
            [['fu', 'bar'], '[[Bar|fu]]'],
            [['fu', '[[Bar]]'], '[[Bar|fu]]'], // Erreur "|lien auteur=[[Bla]]"
        ];
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
     *
     * @param string $text
     * @param string $expected
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
            ['{{Lien|Jeffrey Robinson}}', 'Jeffrey Robinson'],
        ];
    }
}
