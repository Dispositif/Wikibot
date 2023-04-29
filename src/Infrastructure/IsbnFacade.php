<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\InfrastructurePorts\IsbnConverterInterface;

class IsbnFacade implements IsbnConverterInterface
{
    public const ERROR_EMPTY = 'aucun code fourni';

    public const ERROR_INVALID_CHARACTERS = 'caractères invalides';

    public const ERROR_INVALID_LENGTH = 'trop court ou trop long';

    public const ERROR_INVALID_PRODUCT_CODE = 'code produit devrait être 978 ou 979';

    public const ERROR_INVALID_COUNTRY_CODE = 'code pays inconnu';

    // TODO: complete array.
    public const ISBN_LANGUAGE_CODES
        = [
            '0' => 'en',
            '1' => 'en',
            '2' => 'fr',
            '3' => 'de',
            '4' => 'ja',
            '5' => 'ru',
            '88' => 'it',
        ];

    public static function isbn2ean(string $isbn): string
    {
        return preg_replace('#[^0-9X]#i', '', $isbn);
    }

    public function getCountryShortName(): ?string
    {
        return null;
//        $langCode = $this->getCountry() ?? '';
//        if (empty($langCode)) {
//            return null;
//        }
//
//        if (array_key_exists($langCode, self::ISBN_LANGUAGE_CODES)) {
//            return self::ISBN_LANGUAGE_CODES[$langCode];
//        }
//
//        return null;
    }
}
