<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Models\Wiki\OuvrageClean;
use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Infrastructure\GoogleBooksAdapter;
use App\Infrastructure\OpenLibraryAdapter;

class OuvrageFactory
{
    private function __construct()
    {
    }

    public static function OpenLibraryFromIsbn(string $isbn): ?OuvrageTemplate
    {
        $import = new ImportOuvrageFromApi(new OuvrageClean(), new OpenLibraryAdapter());
        $import->hydrateFromIsbn($isbn);

        return $import->getOuvrage();
    }

    public static function GoogleFromIsbn(string $isbn): OuvrageTemplate
    {
        $import = new ImportOuvrageFromApi(new OuvrageClean(), new GoogleBooksAdapter());
        $import->hydrateFromIsbn($isbn);

        return $import->getOuvrage();
    }

    public static function OptimizedFromData(array $data): OuvrageTemplate
    {
        $ouvrage = new OuvrageClean();
        $ouvrage->hydrate($data);
        $proc = new OuvrageOptimize($ouvrage);
        $proc->doTasks();

        return $proc->getOuvrage();
    }
}
