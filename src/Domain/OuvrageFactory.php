<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Models\Wiki\OuvrageClean;
use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\WikiOptimizer\OptimizerFactory;
use App\Infrastructure\BnfAdapter;
use App\Infrastructure\GoogleBooksAdapter;
use App\Infrastructure\OpenLibraryAdapter;
use Psr\Log\LoggerInterface;

/**
 * todo hexa inject di (or move factory ?)
 */
class OuvrageFactory
{
    private function __construct()
    {
    }

    public static function OpenLibraryFromIsbn(string $isbn): ?OuvrageTemplate
    {
        $import = new ImportOuvrageFromApi(new OuvrageClean(), new OpenLibraryAdapter());
        $import->hydrateFromIsbn($isbn);
        $ouvrage = $import->getOuvrage();
        $ouvrage->setDataSource('OL');

        return $ouvrage;
    }

    public static function GoogleFromIsbn(string $isbn): OuvrageTemplate
    {
        $import = new ImportOuvrageFromApi(new OuvrageClean(), new GoogleBooksAdapter());
        $import->hydrateFromIsbn($isbn);
        $ouvrage = $import->getOuvrage();
        $ouvrage->setDataSource('GB');

        return $ouvrage;
    }

    public static function BnfFromIsbn(string $isbn): OuvrageTemplate
    {
        $import = new ImportOuvrageFromApi(new OuvrageClean(), new BnfAdapter());
        $import->hydrateFromIsbn($isbn);
        $ouvrage = $import->getOuvrage();
        $ouvrage->setDataSource('BnF');

        return $ouvrage;
    }

    public static function OptimizedFromData(array $data, LoggerInterface $logger = null): OuvrageTemplate
    {
        $ouvrage = new OuvrageClean();
        $ouvrage->hydrate($data);
        $proc = OptimizerFactory::fromTemplate($ouvrage, null, $logger);
        $proc->doTasks();

        return $proc->getOptiTemplate();
    }
}
