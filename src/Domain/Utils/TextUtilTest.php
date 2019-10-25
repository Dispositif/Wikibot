<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
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
            TextUtil::predictCorrectParam('autuer', ['auteur','bla','hautour'])
        );

    }
}
