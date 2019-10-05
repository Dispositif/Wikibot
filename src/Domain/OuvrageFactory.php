<?php


namespace App\Domain;


use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Infrastructure\GoogleBooksAdapter;
use App\Infrastructure\OpenLibraryAdapter;

class OuvrageFactory
{
    private function __construct() { }

    static public function OpenLibraryFromIsbn(string $isbn): OuvrageTemplate
    {
        $ouvrage = new OuvrageTemplate();
        $map = new OuvrageFromApi($ouvrage, new OpenLibraryAdapter());
        $map->hydrateFromIsbn($isbn);

        return $ouvrage;
    }

    static public function GoogleFromIsbn(string $isbn): OuvrageTemplate
    {
        $ouvrage = new OuvrageTemplate();
        $map = new OuvrageFromApi($ouvrage, new GoogleBooksAdapter());
        $map->hydrateFromIsbn($isbn);

        return $ouvrage;
    }
}
