<?php

namespace App\Application;

use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\OuvrageFactory;
use App\Domain\OuvrageFromApi;
use App\Domain\WikiTextUtil;
use App\Infrastructure\GoogleBooksAdapter;
use App\Infrastructure\OpenLibraryAdapter;

include 'myBootstrap.php';

//{{ouvrage | auteur = Gérard Colin | titre = Alexandre le Grand | éditeur = Pygmalion | année = 2007 | pages = 288 pages | isbn = 9782756400419 }}
//Alexandre le Grand : {{ouvrage | auteur = Paul-André Claudel | titre=Alexandrie | sous-titre=Histoire d'un mythe | éditeur =Ellipses| année = 2011 |isbn=978-2729-866303 }}
$raw
    = '{{Ouvrage |langue=en |auteur1=Calvin Poole |titre=Catucto: Battle Harbour Labrador 1832-1833 |sous-titre= |éditeur=Breakwater Books Ltd. |collection= |lieu=[[Canada]] |année=1996 |volume= |tome= |pages totales=134 |passage= |isbn=978-1550811414 |lire en ligne= }}';


$parse = WikiTextUtil::parseAllTemplateByName('ouvrage', $raw);
/**
 * @var $ouvrage OuvrageTemplate
 */
$ouvrage = $parse['ouvrage'][0]['model'];
dump($raw);
dump($ouvrage);

$isbn = $ouvrage->getParam('isbn');
if(empty($isbn)) {
    dump('no isbn');die;
}

$ol = OuvrageFactory::OpenLibraryFromIsbn($isbn);
dump('OL', $ol->serialize());


$google = OuvrageFactory::GoogleFromIsbn($isbn);
dump('Google', $google->serialize());
