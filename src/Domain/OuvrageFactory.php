<?php
declare(strict_types=1);

namespace App\Domain;


use App\Domain\Models\Wiki\OuvrageClean;
use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Infrastructure\GoogleBooksAdapter;
use App\Infrastructure\OpenLibraryAdapter;

class OuvrageFactory
{
    private function __construct() { }

    static public function OpenLibraryFromIsbn(string $isbn): OuvrageTemplate
    {
        $ouvrage = new OuvrageClean();
        $map = new OuvrageFromApi($ouvrage, new OpenLibraryAdapter());
        $map->hydrateFromIsbn($isbn);

        return $ouvrage;
    }

    static public function GoogleFromIsbn(string $isbn): OuvrageTemplate
    {
        $ouvrage = new OuvrageClean();
        $map = new OuvrageFromApi($ouvrage, new GoogleBooksAdapter());
        $map->hydrateFromIsbn($isbn);

        return $ouvrage;
    }

    static public function OptimizedFromData(array $data): OuvrageTemplate
    {
        $ouvrage = new OuvrageClean();
        $ouvrage->hydrate($data);
        $proc = new OuvrageOptimize($ouvrage);
        $proc->doTasks();

        return $proc->getOuvrage();
    }

}
