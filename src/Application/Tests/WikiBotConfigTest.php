<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\Tests;

use App\Application\WikiBotConfig;
use PHPUnit\Framework\TestCase;

class WikiBotConfigTest extends TestCase
{
    /**
     * @dataProvider provideEditionRestricted
     *
     * @param      $bool
     * @param      $text
     * @param      $botName
     */
    public function testIsEditionRestricted($bool, $text, $botName = null)
    {
        $this::assertSame($bool, WikiBotConfig::isEditionTemporaryRestrictedOnWiki($text, $botName));
    }

    public function provideEditionRestricted()
    {
        return [
            [false, 'bla bla'],
            [true, '{{Protection|blabla}} bla'],
            [true, '{{R3R}} bla'],
            [true, '{{nobots}} bla'],
            [true, '{{bots|deny=Bob,CodexBot}} bla', 'CodexBot'],
            [false, '<!-- {{bots|deny=Bob,CodexBot}} --> bla', 'CodexBot'],
            [false, '{{bots|deny=Bob,FuBot}} bla', 'CodexBot'],
        ];
    }
}
