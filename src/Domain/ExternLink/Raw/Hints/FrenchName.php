<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink\Raw\Hints;

/**
 * Shared by AuthorPrefixExtractor (before the bracket) and
 * AuthorMentionAfterCommaExtractor (after it) : the "Capitalized Word(s)" shape of a
 * plain-text author/institution name, with lowercase connectors ("de"/"du"/"van"/
 * "von"/"der"/"la"/"le") allowed between capitalized words -- "Daniel Louis Olivier
 * Miorcec de Kerdanet" matches as one name, not stopping at "de".
 */
final class FrenchName
{
    private const CONNECTORS = 'de|du|des|van|von|der|la|le';

    public const NAME_PATTERN = "[A-ZÀ-Ý][\p{L}'.\-]*(?:\s+(?:[A-ZÀ-Ý][\p{L}'.\-]*|" . self::CONNECTORS . "))*";
}
