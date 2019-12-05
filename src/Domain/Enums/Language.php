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
 * Class Lang
 *
 * @package App\Domain\Enums
 */
abstract class Language
{
    const dataFile = __DIR__.'/languageData.php';

    /**
     * only static call
     */
    private function __construct() { }

    /**
     * @param string $lang
     *
     * @return bool
     */
    private static function isWikiLang(string $lang): bool
    {
        require self::dataFile;
        /**
         * @var $liste_frlang []
         */
        if (isset($liste_frlang) && in_array($lang, $liste_frlang)) {
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
        require self::dataFile;
        $lang = strtolower($lang);
        if (isset($iso2b_to_french) && array_key_exists($lang, $iso2b_to_french)) {
            if (!empty($iso2b_to_french[$lang])) {
                return self::longFrench2wiki($iso2b_to_french[$lang]);
            }
        }

        return null;
    }

    public static function english2wiki(string $lang): ?string
    {
        require self::dataFile;

        $lang = ucfirst($lang);
        if (isset($english_to_french) && array_key_exists($lang, $english_to_french)) {
            return self::longFrench2wiki($english_to_french[$lang]);
        }
        // ugly
        $lang = ucfirst(mb_strtolower($lang));
        if (isset($english_to_french) && array_key_exists($lang, $english_to_french)) {
            return self::longFrench2wiki($english_to_french[$lang]);
        }

        return null;
    }

    private static function longFrench2wiki(string $lang): ?string
    {
        require self::dataFile;
        $lang = mb_strtolower($lang);
        if (isset($french_to_frlang) && array_key_exists($lang, $french_to_frlang)) {
            return $french_to_frlang[$lang];
        }

        return null;
    }

}
