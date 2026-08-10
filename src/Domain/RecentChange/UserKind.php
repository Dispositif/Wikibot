<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2026 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\RecentChange;

/**
 * MediaWiki's own rcprop=flags "anon" marker does NOT identify temporary accounts —
 * confirmed on live data (~2026-42871-93 came back as plain "registered"). Temp
 * accounts are only identifiable by their username shape, so Temp is detected by
 * pattern rather than by API flag — see fromUsername().
 */
enum UserKind: string
{
    case Registered = 'registered';
    case Ip = 'ip';
    case Bot = 'bot';
    case Temp = 'temp';

    /**
     * Temporary-account username : "~" + 4-digit creation year + one or more groups of
     * up to 5 digits separated by hyphens. Active on frwiki since 2025-06-24, unrenamable
     * by design. https://fr.wikipedia.org/wiki/Wikip%C3%A9dia:Compte_temporaire
     * Examples (from that page): ~2024-0000, ~2025-00000-000, ~2025-00000-00000-0.
     */
    private const TEMP_USERNAME_PATTERN = '/^~\d{4}(-\d{1,5})+$/';

    public static function fromUsername(string $username, bool $isAnon, bool $isBot): self
    {
        if ($isBot) {
            return self::Bot;
        }
        if (preg_match(self::TEMP_USERNAME_PATTERN, $username) === 1) {
            return self::Temp;
        }
        if ($isAnon) {
            return self::Ip;
        }

        return self::Registered;
    }
}
