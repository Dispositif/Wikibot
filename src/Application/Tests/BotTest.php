<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application\Tests;

use App\Application\WikiBotConfig;
use PHPUnit\Framework\TestCase;

class BotTest extends TestCase
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
        $this::assertSame($bool, WikiBotConfig::isEditionRestricted($text, $botName));
    }

    public function provideEditionRestricted()
    {
        return [
            [false, 'bla bla'],
            [true, '{{Protection|blabla}} bla'],
            [true, '{{3R}} bla'],
            [true, '{{nobots}} bla'],
            [true, '{{bots|deny=Bob,FuBot}} bla', 'FuBot'],
            [false, '{{bots|deny=Bob,FuBot}} bla'],
        ];
    }
}
