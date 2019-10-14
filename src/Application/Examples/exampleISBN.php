<?php
declare(strict_types=1);

namespace App\Application\Examples;

use App\Domain\Models\Wiki\GoogleLivresTemplate;
use App\Domain\Models\Wiki\OuvrageClean;
use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\OuvrageComplete;
use App\Domain\OuvrageFactory;
use App\Domain\ImportOuvrageFromApi;
use App\Domain\OuvrageOptimize;
use App\Domain\Utils\TextUtil;
use App\Domain\Utils\TemplateParser;
use App\Infrastructure\CorpusAdapter;
use App\Infrastructure\GoogleBooksAdapter;
use App\Infrastructure\MessageAdapter;
use App\Infrastructure\OpenLibraryAdapter;


include __DIR__.'/../myBootstrap.php';


// COOL
// {{Ouvrage|langue=fr|auteur1=Ernest Nègre|titre=Toponymie générale de la France|tome=3|passage=1527|isbn=2600028846}}


// TODO
// google : 1) Détect url google (+page) 2) convert {{Google Livres}} 2) détect si PARTIAL : lire en ligne=>présentation
// date - année
// auteur1 <> prénom/nom
// si auteur= alors faut bloquer setParam('auteur1')

// Sagrada Família

//{{Ouvrage|langue=es |nom1=Fargas |prénom1=Albert |année=2009 |titre=Simbología del Templo de la Sagrada Familia |édition=Triangle Postals |lieu=Barcelone |isbn=978-84-8478-405-0}}
//Sagrada Família : {{Ouvrage|langue=es |nom1=Giralt-Miracle |prénom1=Daniel |année=2002 |titre={{lang|es|Gaudí, la busqueda de la forma}} |éditeur=Lunwerg |lieu=Barcelone |isbn=84-7782-724-9}}
//Sagrada Família : {{Ouvrage|langue=es |nom1=Gómez Gimeno |prénom1=María José |année=2006 |titre=La Sagrada Familia |édition={{lang|ca|Mundo Flip Ediciones}} |isbn=84-933983-4-9}}
//Sagrada Família : {{Ouvrage|langue=es |nom1=Van Zandt |prénom1=Eleanor |année=1997 |titre=la vida y obras de Gaudí |édition=Asppan |isbn=0-7525-1106-8}}

//Sagrada Família :
//Sagrada Família : {{Ouvrage|langue=fr |nom1=Crippa |prénom1=Maria Antonietta |année=2007 |titre=Gaudí |édition=Taschen |lieu=Cologne |passage=84 |isbn=978-3822825204}}
//Sagrada Família : {{ouvrage|langue=fr |prénom1=Ricardo |nom1=Regàs |titre=Antoni Gaudi |édition=Dosde arte Ediciones |année=2011 |isbn=978-84-96783-40-9 }}

$raw
    = '{{Ouvrage|langue=es |titre=El juego del ángel |prénom=Carlos |nom=Ruiz Zafón |lien auteur1=Carlos Ruiz Zafón |isbn=978-2221111697}}';
// https://books.google.es/books?id=RhbSPdHWeD0C

//$mess = new MessageAdapter();
//$mess->send('test', 'bla');
//$mess->send('ISBN invalide', 'blabla');
//$mess->send('rabbit', 'CACA');

dump(json_encode([
    'Discussion utilisateur:ZiziBot' => '2019-10-06T20:15:42Z',
    'Discussion utilisateur:Irønie' => '2019-09-18T22:12:52Z',
]));die;

$log = [];

$parse = TemplateParser::parseAllTemplateByName('ouvrage', $raw);
/**
 * @var $ouvrage OuvrageTemplate
 * @var $origin  OuvrageTemplate
 */
$origin = $parse['ouvrage'][0]['model'];
dump($raw);


$opti = (new OuvrageOptimize($origin))->doTasks();
dump($opti->getLog());
$log += $opti->getLog();

$ouvrage = $opti->getOuvrage();
dump($ouvrage->serialize(false));

$isbn = $ouvrage->getParam('isbn');
if (empty($isbn)) {
    dump('no isbn');
    die;
}

sleep(5);

// récup Google
dump('***** GOOGLE *****');
$google = OuvrageFactory::GoogleFromIsbn($isbn);
$google2 = (new OuvrageOptimize($google))->doTasks()->getOuvrage();
dump($google->serialize());
dump('opti', $google2->serialize());

// complete origin avec Google
$a = new OuvrageComplete($ouvrage, $google2);
$fin = $a->getResult();
dump('Completed with Google :');
dump($fin->serialize(false));
dump($a->getLog());
$log += $a->getLog();
dump('isParamValueEquals', $origin->isParamValueEquals($fin));


// complete origin avec OL et l'affiche
dump('********** OpenLibrary');
$ol = OuvrageFactory::OpenLibraryFromIsbn($isbn);
if (!empty($ol)) {
    dump($ol->serialize());
    $a = new OuvrageComplete($ouvrage, $ol);
    dump($a->getResult()->serialize());

    // complete ensuite avec OpenLib
    $a = new OuvrageComplete($fin, $ol);
    $fin = $a->getResult();
    dump($a->getLog());
    $log += $a->getLog(); // TODO "not same book"
    dump('isParamValueEquals', $origin->isParamValueEquals($fin));

    dump('final', dump($fin->serialize(true)));
}




