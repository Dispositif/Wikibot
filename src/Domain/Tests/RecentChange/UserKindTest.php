<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2026 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Tests\RecentChange;

use App\Domain\RecentChange\UserKind;
use PHPUnit\Framework\TestCase;

class UserKindTest extends TestCase
{
    public function testBotFlagWinsOverEverything()
    {
        $this::assertSame(UserKind::Bot, UserKind::fromUsername('~2025-00000-000', true, true));
    }

    /**
     * @dataProvider temporaryAccountUsernames
     */
    public function testTemporaryAccountUsernamesAreDetected(string $username)
    {
        $this::assertSame(UserKind::Temp, UserKind::fromUsername($username, false, false));
    }

    public static function temporaryAccountUsernames(): array
    {
        // Examples from https://fr.wikipedia.org/wiki/Wikip%C3%A9dia:Compte_temporaire
        return [
            ['~2024-0000'],
            ['~2025-00000-000'],
            ['~2025-00000-00000-0'],
            ['~2026-42871-93'], // observed live on frwiki
        ];
    }

    public function testTemporaryAccountPatternWinsOverAnonFlag()
    {
        // A temp account isn't flagged "anon" by the API (confirmed live) — but even if
        // it were, the username shape should still take precedence.
        $this::assertSame(UserKind::Temp, UserKind::fromUsername('~2025-00000-000', true, false));
    }

    /**
     * @dataProvider nonTemporaryUsernames
     */
    public function testUsernamesNotMatchingThePatternAreNotClassifiedAsTemp(string $username)
    {
        $this::assertNotSame(UserKind::Temp, UserKind::fromUsername($username, false, false));
    }

    public static function nonTemporaryUsernames(): array
    {
        return [
            ['SomeEditor'],
            ['1.2.3.4'],
            ['~SomeSignatureLookingThing'],
            ['~2025'], // no digit group at all
            ['~20-0000'], // year not 4 digits
            ['~2025-123456'], // group over 5 digits
        ];
    }

    public function testAnonFlagMapsToIpWhenNotTempPattern()
    {
        $this::assertSame(UserKind::Ip, UserKind::fromUsername('1.2.3.4', true, false));
    }

    public function testNoFlagsMapsToRegistered()
    {
        $this::assertSame(UserKind::Registered, UserKind::fromUsername('SomeEditor', false, false));
    }
}
