<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 (c) Philippe M. <dispositif@gmail.com>
 * For the full copyright and license information, please view the LICENSE file
 */

declare(strict_types=1);

namespace App\Domain;

use Biblys\Isbn\Isbn;

class IsbnFacade extends Isbn
{
    const ERROR_EMPTY = 'aucun code fourni';

    const ERROR_INVALID_CHARACTERS = 'caractères invalides';

    const ERROR_INVALID_LENGTH = 'trop court ou trop long';

    const ERROR_INVALID_PRODUCT_CODE = 'code produit devrait être 978 ou 979';

    const ERROR_INVALID_COUNTRY_CODE = 'code pays inconnu';

    public static function isbn2ean(string $isbn)
    {
        return preg_replace('#[^0-9X]#i', '', $isbn);
    }
}
