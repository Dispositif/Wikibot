<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application\Examples;

use App\Application\PublisherAction;
use App\Domain\Models\Wiki\ArticleTemplate;
use App\Domain\Models\Wiki\LienWebTemplate;
use App\Domain\Models\Wiki\OuvrageTemplate;
use App\Domain\Publisher\WebMapper;
use App\Domain\WikiTemplateFactory;
use Symfony\Component\Yaml\Yaml;

require_once __DIR__.'/../myBootstrap.php';

//$url = 'https://www.20minutes.fr/sante/2744087-20200320-coronavirus-o-livraison-masques-tant-attendue-soignants';
//$url = 'https://www.monde-diplomatique.fr/2019/12/RZEPSKI/61104';
//$url = 'https://www.lemonde.fr/societe/article/2016/04/21/un-orchestre-amateur-interprete-la-symphonie-du-nouveau-monde-devant-la-nuit-debout-a-paris_4905754_3224.html';
//$url = 'https://www.la-croix.com/Religion/Catholicisme/France/Trois-quadras-lancent-laventure-rachat-Peuples-Monde-2019-07-04-1201033387';
//$url = 'https://www.camptocamp.org/waypoints/39005/fr/miroir-d-argentine';

/**
 * ArticleNews
 * https://www.lemonde.fr/cinema/article/2018/10/31/fahrenheit-11-9-donald-trump-dans-le-viseur-de-michael-moore_5376892_3476.html
 */

$url = $argv[1];
if (empty($url)) {
    die("php testPress.php 'http://...'\n");
}

// -___________________________
$config = Yaml::parseFile(__DIR__.'/config_presse.yaml');

$parseURL = parse_url($url);
$domain = str_replace('www.', '', $parseURL['host']);
// -___________________________


// check Domain validated
if (!isset($config[$domain])) {
    dump("DOMAIN $domain PAS AUTORISE DANS LA CONFIG");
    sleep(3);
}

$pref = $config[$domain] ?? [];
$pref = is_array($pref) ? $pref : [];

if($pref === 'desactived' || isset($pref['desactived'])) {
    dump('DESACTIVED');
    die;
}


$publish = new PublisherAction($url);
try{
    $html = $publish->getHTMLSource();
    $data = $publish->extractWebData($html);
}catch (\Throwable $e){
    // TODO : reprendre ArticleFromUrl:65
    throw $e;
}

dump($data);

$genericMapper = new WebMapper();
$res = $genericMapper->process($data);
dump($res);

// Logique : choix template
$pref['template'] = $pref['template'] ?? [];
$res['DATA-ARTICLE'] = $res['DATA-ARTICLE'] ?? false;
if ($pref['template'] === 'article'
    || ($pref['template'] === 'auto' && $res['DATA-ARTICLE'])
) {
    $templateName = 'article';
}
if (!isset($templateName) || $pref['template'] === 'lien web') {
    $templateName = 'lien web';
}
$template = WikiTemplateFactory::create($templateName);
$template->userSeparator = " |";



// Logique : remplacement titre périodique ou nom du site

if (!empty($pref['site']) &&  $template instanceof LienWebTemplate) {
    $res['site'] = $pref['site'];
}
if (!empty($pref['périodique']) && (!empty($res['périodique']) || $template instanceof OuvrageTemplate)) {
    $res['périodique'] = $pref['périodique'];
}
//if (!empty($pref['éditeur']) ) {
//    // Persée
//    $res['éditeur'] = $pref['éditeur'];
//}

dump('config', $pref);

$template->hydrate($res, true);
echo $template->serialize(true)."\n";




