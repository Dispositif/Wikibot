<?php
declare(strict_types=1);

namespace App\Application;

use App\Domain\Models\Wiki\GoogleLivresTemplate;
use App\Domain\Models\Wiki\OuvrageClean;
use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\OuvrageComplete;
use App\Domain\OuvrageFactory;
use App\Domain\ImportOuvrageFromApi;
use App\Domain\OuvrageOptimize;
use App\Domain\Utils\TextUtil;
use App\Domain\Utils\WikiTextUtil;
use App\Infrastructure\CorpusAdapter;
use App\Infrastructure\GoogleBooksAdapter;
use App\Infrastructure\MessageAdapter;
use App\Infrastructure\OpenLibraryAdapter;


include 'myBootstrap.php';



//{{ouvrage | auteur = Gérard Colin | titre = Alexandre le Grand | éditeur = Pygmalion | année = 2007 | pages = 288 pages | isbn = 9782756400419 }}
//Alexandre le Grand : {{ouvrage | auteur = Paul-André Claudel | titre=Alexandrie | sous-titre=Histoire d'un mythe | éditeur =Ellipses| année = 2011 |isbn=978-2729-866303 }}

// COOL
// {{Ouvrage|langue=fr|auteur1=Ernest Nègre|titre=Toponymie générale de la France|tome=3|passage=1527|isbn=2600028846}}




//Saint-Aignan-le-Jaillard : {{Ouvrage |prénom1= Jean-Louis |nom1=Masson | titre = Provinces, départements, régions : l'organisation administrative de la France| éditeur = Fernand Lanore| lieu = | année = 1984 | isbn = 285157003X| pages totales = 703|lire en ligne=https://books.google.fr/books?id=pbspjvZst5UC&pg=PA395&lpg=PA395&dq=D%C3%A9cret-Loi+10+septembre+1926&source=bl&ots=kiCzMrHO7b&sig=Jxt2Ybpig7Oo-Mtuzgp_sL5ipQ4&hl=fr&sa=X&ei=6SMLU_zIDarL0AX75YAI&ved=0CFEQ6AEwBA#v=onepage&q=D%C3%A9cret-Loi%2010%20septembre%201926&f=false}}
$raw
    = ' 
Saint-Aignan-le-Jaillard : {{Ouvrage |id =Bonneton|nom1=Collectif | titre = Loiret : un département à l\'élégance naturelle | éditeur = Christine Bonneton | lieu = Paris | année = 2 septembre 1998 | isbn = 978-2-86253-234-9| pages totales = 319}}';

// https://books.google.fr/books?id=pbspjvZst5UC&pg=PA395&lpg=PA395&dq=D%C3%A9cret-Loi+10+septembre+1926&source=bl&ots=kiCzMrHO7b&sig=Jxt2Ybpig7Oo-Mtuzgp_sL5ipQ4&hl=fr&sa=X&ei=6SMLU_zIDarL0AX75YAI&ved=0CFEQ6AEwBA#v=onepage&q=D%C3%A9cret-Loi%2010%20septembre%201926&f=false




// https://books.google.fr/books?id=pbspjvZst5UC&pg=PA395&lpg=PA395&dq=D%C3%A9cret-Loi+10+septembre+1926&source=bl&ots=kiCzMrHO7b&sig=Jxt2Ybpig7Oo-Mtuzgp_sL5ipQ4&hl=fr&sa=X&ei=6SMLU_zIDarL0AX75YAI&ved=0CFEQ6AEwBA#v=onepage&q=D%C3%A9cret-Loi%2010%20septembre%201926&f=false

// https://books.google.fr/books?id=pbspjvZst5UC&pg=PA395&lpg=PA395&lp&dq=D%C3%A9cret-Loi+10+septembre+1926&q=D%C3%A9cret-Loi%2010%20septembre%201926
// https://books.google.fr/books?id=pbspjvZst5UC&pg=PA395&dq=D%C3%A9cret-Loi+10+septembre+1926

// TODO
// google : 1) Détect url google (+page) 2) convert {{Google Livres}} 2) détect si PARTIAL : lire en ligne=>présentation
// date - année
// auteur1 <> prénom/nom
// si auteur= alors faut bloquer setParam('auteur1')

$raw = '{{Ouvrage |id =Bonneton|nom1=Collectif | titre = Loiret : un département à l\'élégance naturelle | éditeur = Christine Bonneton | lieu = Paris | année = 2 septembre 1998 | isbn = 978-2-86253-234-9| pages totales = 319}}';
//Sagrada Família : {{Ouvrage|langue=es |nom1=Gómez Gimeno |prénom1=María José |année=2006 |titre=La Sagrada Familia |édition={{lang|ca|Mundo Flip Ediciones}} |isbn=84-933983-4-9}}'


$mess = new MessageAdapter();
$mess->send('test','bla');
$mess->send('rabbit', 'CACA');
dump($mess);
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
dump($ouvrage->serialize(true));

$isbn = $ouvrage->getParam('isbn');
if(empty($isbn)) {
    dump('no isbn');die;
}


// récup Google
dump('***** GOOGLE *****');
$google = OuvrageFactory::GoogleFromIsbn($isbn);
$google2 = (new OuvrageOptimize($google))->doTasks()->getOuvrage();
dump($google->serialize());
dump('opti', $google2->serialize());

// complete origin avec Google
$a = new OuvrageComplete($ouvrage, $google2);
$fin = $a->getResult();
dump($fin->serialize());
dump($a->getLog());
dump('isParamValueEquals', $origin->isParamValueEquals($fin));


// complete origin avec OL et l'affiche
dump('********** OpenLibrary');
$ol = OuvrageFactory::OpenLibraryFromIsbn($isbn);
if(!empty($ol)){
    dump($ol->serialize());
    $a = new OuvrageComplete($ouvrage, $ol);
    dump($a->getResult()->serialize());

    // complete ensuite avec OpenLib
    $a = new OuvrageComplete($fin, $ol);
    $fin = $a->getResult();
    dump($a->getLog());
    dump('isParamValueEquals', $origin->isParamValueEquals($fin));

    dump('final', dump($fin->serialize(true)));
}



