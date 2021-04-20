<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe/Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Utils;

use PHPUnit\Framework\TestCase;

class TextUtilTest extends TestCase
{
    public function testBreakingSpace()
    {
        $html = "bla&nbsp;bla\n";
        $text = html_entity_decode($html);
        $this::assertSame(
            'bla bla',
            TextUtil::trim(TextUtil::replaceNonBreakingSpaces($text))
        );
    }

    public function testMb_ucfirst()
    {
        $this::assertSame(
            'Économie galante',
            TextUtil::mb_ucfirst('économie galante')
        );
    }

    public function testStripPunctuation()
    {
        $this::assertSame(
            'blabla',
            TextUtil::stripPunctuation('bla¦}©§bla')
        );
    }

    public function testStripAccents()
    {
        $this::assertSame(
            'Ecealo',
            TextUtil::stripAccents('Écéàlô')
        );
    }

    public function testPredictCorrectParam()
    {
        $this::assertSame(
            'auteur',
            TextUtil::predictCorrectParam('autuer', ['auteur', 'bla', 'hautour'])
        );
    }

    public function testStrEndsWith()
    {
        $this::assertSame(
            true,
            TextUtil::str_ends_with('testaà', 'aà')
        );
        $this::assertSame(
            false,
            TextUtil::str_ends_with('testaà', 'test')
        );
        $this::assertSame(
            true,
            TextUtil::str_ends_with('testaà', '')
        );
    }
    public function testStrStqrtsWith()
    {
        $this::assertSame(
            true,
            TextUtil::str_starts_with('téstaà', 'tést')
        );
        $this::assertSame(
            false,
            TextUtil::str_starts_with('téstaa', 'aa')
        );
        $this::assertSame(
            true,
            TextUtil::str_starts_with('téstaa', '')
        );
    }
}
