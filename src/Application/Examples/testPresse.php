<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application\Examples;

use App\Application\Color;
use App\Application\RefWebTransformer;
use App\Infrastructure\Logger;

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

echo Color::BG_LIGHT_RED.$url.Color::NORMAL."\n";

$log = new Logger();
$log->debug = true;
$log->verbose = true;
$trans = new RefWebTransformer($log);
$trans->skipUnauthorised = false;
$trans->verbose = true;
$result = $trans->process($url);

echo $result."\n";





