<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink\Raw\Hints;

/**
 * Shared by hint extractors that capture a site/périodique name possibly wrapped in
 * italics and/or a wikilink -- "''Le Monde''", "[[Le Monde]]", "''[[Institut national de
 * l'audiovisuel|INA]]''" (piped wikilink nested inside italics, order matters : strip
 * italics first so the wikilink regex then sees a clean "[[...]]").
 */
final class WikiMarkupStripper
{
    public static function stripItalicAndWikilink(string $value): string
    {
        $value = trim($value);
        if (preg_match("#^''(.+)''\$#u", $value, $m)) {
            $value = trim($m[1]);
        }
        if (preg_match('#^\[\[(?:[^|\]]*\|)?([^]]+)]]$#u', $value, $m)) {
            $value = trim($m[1]);
        }

        return $value;
    }
}
