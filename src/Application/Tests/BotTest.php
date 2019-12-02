<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);


namespace App\Application\Tests;

use App\Application\Bot;
use PHPUnit\Framework\TestCase;

class BotTest extends TestCase
{

    public function testIsEditionRestricted()
    {
        $this::assertFalse(Bot::isEditionRestricted('bla bla'));
        $this::assertTrue(Bot::isEditionRestricted('{{Protection|blabla}} bla'));
        $this::assertTrue(Bot::isEditionRestricted('{{3R}} bla'));
        $this::assertTrue(Bot::isEditionRestricted('{{nobots}} bla'));
        $this::assertTrue(Bot::isEditionRestricted('{{bots|deny=Bob,FuBot}} bla', 'FuBot'));
        $this::assertFalse(Bot::isEditionRestricted('{{bots|deny=Bob,FuBot}} bla'));;
    }
}
