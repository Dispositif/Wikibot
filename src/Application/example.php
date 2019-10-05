<?php

namespace App\Application;

use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\OuvrageFromApi;
use App\Domain\WikiTextUtil;
use App\Infrastructure\GoogleBooksAdapter;

include 'myBootstrap.php';

$raw
    = 'Alexandre des Pays-Bas (1818-1848) : {{Ouvrage|prénom1=Thera|nom1=Coppens|titre=Sophie in Weimar. Een prinses van Oranje in Duitsland|lieu=Amsterdam|éditeur=Meulenhoff|année=2011|isbn=978-90-290-8743-8}}';


$parse = WikiTextUtil::parseAllTemplateByName('ouvrage', $raw);

/**
 * @var $ouvrage OuvrageTemplate
 */
$ouvrage = $parse['ouvrage'][0]['model'];
dump($raw);
dump($ouvrage->serialize());

$googleOuvrage = new OuvrageTemplate();
$map = new OuvrageFromApi($googleOuvrage, new GoogleBooksAdapter());
$map->hydrateFromIsbn($ouvrage->getParam('isbn'));
dump($googleOuvrage->serialize());
