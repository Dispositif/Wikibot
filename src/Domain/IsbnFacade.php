<?php

declare(strict_types=1);

namespace App\Domain;

use Biblys\Isbn\Isbn;

class IsbnFacade extends Isbn
{
    public static function isbn2ean(string $isbn)
    {
        return preg_replace('#[^0-9X]#i', '', $isbn);
    }

    // todo : Biblys/Isbn : "static::" pour late static binding des messages sur classes filles
    public function translateMessageFr(string $message)
    {
        return str_replace(
            [
                'No code provided',
                'Invalid characters in the code',
                'Code is too short or too long',
                'Product code should be 978 or 979',
                'Country code is unknown',
            ],
            [
                'aucun code fourni',
                'caractères invalides',
                'code trop court ou trop long',
                'code produit doit être 978 ou 979',
                'code pays inconnu',
            ],
            $message
        );
    }
}
