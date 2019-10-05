<?php

namespace App\Application;

use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\OuvrageFromApi;
use App\Domain\WikiTextUtil;
use App\Infrastructure\GoogleBooksAdapter;
use App\Infrastructure\OpenLibraryAdapter;

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


$ol = new OuvrageTemplate();
$map = new OuvrageFromApi($ol, new OpenLibraryAdapter());
$map->hydrateFromIsbn($ouvrage->getParam('isbn'));
dump($ol->serialize());
die;

//$ol = new OpenLibraryAdapter();
//$dat = $ol->getDataByIsbn('978-90-290-8743-8');
//dump($dat);die;



$googleOuvrage = new OuvrageTemplate();
$map = new OuvrageFromApi($googleOuvrage, new GoogleBooksAdapter());
$map->hydrateFromIsbn($ouvrage->getParam('isbn'));
dump($googleOuvrage->serialize());
