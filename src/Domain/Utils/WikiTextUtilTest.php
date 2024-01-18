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

    /**
     * @dataProvider provideContainsWikiTag
     */
    public function testContainsWikiTag(string $text, bool $expected)
    {
        $this::assertSame(
            $expected,
            WikiTextUtil::containsWikiTag($text)
        );
    }

    public static function provideContainsWikiTag(): array
    {
        return [
            ['http://bla', false],
            ['http://bla</ref>', true],
            ['http://bla<ref name="bla">', true],
            ['http://bla<nowiki>', true],
        ];
    }

    /**
     * @dataProvider provideExtractCommentedText
     */
    public function testExtractCommentedText(string $text, array $expected): void
    {
        $this::assertSame(
            $expected,
            WikiTextUtil::extractCommentedText($text)
        );
    }

    public static function provideExtractCommentedText(): array
    {
        return [
            ['bla <!-- fu --> bla <!-- bar --> bla', ['<!-- fu -->', '<!-- bar -->']],
            ['bla <!-- fu --> pof <!-- bar --> --> bla', ['<!-- fu -->', '<!-- bar -->']],
            ['bla <!-- fu <!-- bar --> --> bla', ['<!-- fu <!-- bar -->']],
        ];
    }

    public function testFilterSensitiveCommentsInText(): void
    {
        $text = 'bla <!-- 
        * https://skip.com 
        --> bla2 <!-- <ref>skip comment</ref> --> bla3<!-- keep --><!-- {{skip template}} -->';
        $expected = 'bla #FILTERED_COMMENT# bla2 #FILTERED_COMMENT# bla3<!-- keep -->#FILTERED_COMMENT#';
        $this::assertSame(
            $expected,
            WikiTextUtil::filterSensitiveCommentsInText($text)
        );
    }
}
