<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Enums;

/**
 * Convert language name in wiki lang code.
 * Class Lang.
 */
abstract class Language
{
    /**
     * only static call.
     */
    private function __construct()
    {
    }

    /**
     * @param string $lang
     *
     * @return bool
     */
    private static function isWikiLang(string $lang): bool
    {
        /*
         * @var $liste_frlang []
         */
        if (LanguageData::LANG_FRWIKI && in_array($lang, LanguageData::LANG_FRWIKI)) {
            return true;
        }

        return false;
    }

    public static function all2wiki(string $lang): ?string
    {
        $lower = mb_strtolower($lang);
        if (self::isWikiLang($lower)) {
            return $lower;
        }

        if ($dat = self::iso2b2wiki($lang)) {
            return $dat;
        }

        if ($dat = self::english2wiki($lang)) {
            return $dat;
        }

        if ($dat = self::longFrench2wiki($lang)) {
            return $dat;
        }

        return null;
    }

    public static function iso2b2wiki(string $lang): ?string
    {
        $lang = strtolower($lang);
        if (LanguageData::ISO2B_TO_FRENCH && array_key_exists($lang, LanguageData::ISO2B_TO_FRENCH)) {
            if (!empty(LanguageData::ISO2B_TO_FRENCH[$lang])) {
                return self::longFrench2wiki(LanguageData::ISO2B_TO_FRENCH[$lang]);
            }
        }

        return null;
    }

    public static function english2wiki(string $lang): ?string
    {
        $lang = ucfirst($lang);
        if (LanguageData::ENGLISH_TO_FRENCH && array_key_exists($lang, LanguageData::ENGLISH_TO_FRENCH)) {
            return self::longFrench2wiki(LanguageData::ENGLISH_TO_FRENCH[$lang]);
        }
        // ugly
        $lang = ucfirst(mb_strtolower($lang));
        if (LanguageData::ENGLISH_TO_FRENCH && array_key_exists($lang, LanguageData::ENGLISH_TO_FRENCH)) {
            return self::longFrench2wiki(LanguageData::ENGLISH_TO_FRENCH[$lang]);
        }

        return null;
    }

    private static function longFrench2wiki(string $lang): ?string
    {
        $lang = mb_strtolower($lang);
        if (LanguageData::FRENCH_TO_FRWIKI && array_key_exists($lang, LanguageData::FRENCH_TO_FRWIKI)) {
            return LanguageData::FRENCH_TO_FRWIKI[$lang];
        }

        return null;
    }
}
