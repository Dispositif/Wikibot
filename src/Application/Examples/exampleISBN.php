<?php

declare(strict_types=1);

namespace App\Application\Examples;

use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\OuvrageComplete;
use App\Domain\OuvrageFactory;
use App\Domain\OuvrageOptimize;
use App\Domain\Utils\TemplateParser;

include __DIR__.'/../myBootstrap.php';

$raw
    = '{{Ouvrage|langue=fr|auteur1=Ernest Nègre|titre=Toponymie générale de la France|tome=3|passage=1527|isbn=2600028846}}';

//$mess = new MessageAdapter();
//$mess->send('test', 'bla');
//$mess->send('ISBN invalide', 'blabla');
//$mess->send('rabbit', 'CACA');


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




