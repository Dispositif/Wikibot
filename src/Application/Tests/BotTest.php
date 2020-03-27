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
    public function testIsEditionRestricted()
    {
        $this::assertFalse(WikiBotConfig::isEditionRestricted('bla bla'));
        $this::assertTrue(WikiBotConfig::isEditionRestricted('{{Protection|blabla}} bla'));
        $this::assertTrue(WikiBotConfig::isEditionRestricted('{{3R}} bla'));
        $this::assertTrue(WikiBotConfig::isEditionRestricted('{{nobots}} bla'));
        $this::assertTrue(WikiBotConfig::isEditionRestricted('{{bots|deny=Bob,FuBot}} bla', 'FuBot'));
        $this::assertFalse(WikiBotConfig::isEditionRestricted('{{bots|deny=Bob,FuBot}} bla'));
    }
}
