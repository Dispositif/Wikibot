<?php
declare(strict_types=1);

namespace App\Application;

use App\Domain\Models\Wiki\GoogleLivresTemplate;
use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\OuvrageComplete;
use App\Domain\OuvrageFactory;
use App\Domain\OuvrageFromApi;
use App\Domain\OuvrageOptimize;
use App\Domain\WikiTextUtil;
use App\Infrastructure\GoogleBooksAdapter;
use App\Infrastructure\OpenLibraryAdapter;


include 'myBootstrap.php';

//{{ouvrage | auteur = Gérard Colin | titre = Alexandre le Grand | éditeur = Pygmalion | année = 2007 | pages = 288 pages | isbn = 9782756400419 }}
//Alexandre le Grand : {{ouvrage | auteur = Paul-André Claudel | titre=Alexandrie | sous-titre=Histoire d'un mythe | éditeur =Ellipses| année = 2011 |isbn=978-2729-866303 }}

// COOL
// {{Ouvrage|langue=fr|auteur1=Ernest Nègre|titre=Toponymie générale de la France|tome=3|passage=1527|isbn=2600028846}}




//Saint-Aignan-le-Jaillard : {{Ouvrage |prénom1= Jean-Louis |nom1=Masson | titre = Provinces, départements, régions : l'organisation administrative de la France| éditeur = Fernand Lanore| lieu = | année = 1984 | isbn = 285157003X| pages totales = 703|lire en ligne=https://books.google.fr/books?id=pbspjvZst5UC&pg=PA395&lpg=PA395&dq=D%C3%A9cret-Loi+10+septembre+1926&source=bl&ots=kiCzMrHO7b&sig=Jxt2Ybpig7Oo-Mtuzgp_sL5ipQ4&hl=fr&sa=X&ei=6SMLU_zIDarL0AX75YAI&ved=0CFEQ6AEwBA#v=onepage&q=D%C3%A9cret-Loi%2010%20septembre%201926&f=false}}
$raw
    = '  
{{ouvrage|éditeur=Taschen|collection=Mi|série=Manga Design|titre=Manga|nom1=Masanao Amano|préface=Julius Wiedemann|langue=ja|lieu=Cologne|année=2004|mois=mai|jour=15|pages=576|format=broché - 196 x 249 mm|isbn=3-8228-2591-3|passage=198–201|présentation en ligne=http://www.taschen.com/media/downloads/clipping_200410_revista_manga_0707091331_id_66405.pdf|commentaire=édition trilingue : anglais (trad. John McDonald & Tamami Sanbommatsu), français (trad. Marc Combes) et allemand (trad. du japonais par Ulrike Roeckelein) (1 livre + 1 DVD)}}
  ';



// TODO
// google : 1) Détect url google (+page) 2) convert {{Google Livres}} 2) détect si PARTIAL : lire en ligne=>présentation
// date - année
// auteur1 <> prénom/nom
// si auteur= alors faut bloquer setParam('auteur1')

dump(GoogleLivresTemplate::isGoogleBookURL('https://books.google.com/books?id=GI78oVLDoHwC&pg=PA92'));
dump(GoogleLivresTemplate::createFromURL('https://books.google.com/books?id=GI78oVLDoHwC&pg=PA92')->serialize());

die;



$parse = WikiTextUtil::parseAllTemplateByName('ouvrage', $raw);
/**
 * @var $ouvrage OuvrageTemplate
 * @var $origin OuvrageTemplate
 */
$origin = $parse['ouvrage'][0]['model'];
dump($raw);
$opti = (new OuvrageOptimize($origin))->doTasks();
dump($opti->getLog());
$ouvrage = $opti->getOuvrage();
dump($ouvrage->serialize());


$isbn = $ouvrage->getParam('isbn');
if(empty($isbn)) {
    dump('no isbn');die;
}

$ol = OuvrageFactory::OpenLibraryFromIsbn($isbn);
dump('OL', $ol->serialize());
$a = new OuvrageComplete($ouvrage, $ol);
dump($a->getResult()->serialize());


$google = OuvrageFactory::GoogleFromIsbn($isbn);
$google2 = (new OuvrageOptimize($google))->doTasks()->getOuvrage();
dump('Google', $google->serialize());
dump('opti', $google2->serialize());

$a = new OuvrageComplete($ouvrage, $google2);
$fin = $a->getResult();
dump($fin->serialize());
dump('isParamValueEquals', $origin->isParamValueEquals($fin));
