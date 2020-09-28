<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe/Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\Examples;

use App\Application\WikiPageAction;
use App\Infrastructure\ServiceFactory;
use GuzzleHttp\Client;
use Mediawiki\DataModel\EditInfo;
use Simplon\Mysql\Mysql;
use Simplon\Mysql\PDOConnector;
use Throwable;

include __DIR__.'/../ZiziBot_Bootstrap.php'; //myBootstrap.php';

// get DOC content from github
$url = 'https://raw.githubusercontent.com/Dispositif/Wikibot/master/docs/fonctionnalite.wiki';
$response = (new Client())->get($url);
if (200 !== $response->getStatusCode()) {
    die('not 200 response');
}
try {
    /** @noinspection PhpUnusedLocalVariableInspection */
    $newText = $response->getBody()->getContents();
} catch (Throwable $e) {
    dump($e);
    die;
}


$pdo = new PDOConnector(
    getenv('MYSQL_HOST'), getenv('MYSQL_USER'), getenv('MYSQL_PASSWORD'), getenv('MYSQL_DATABASE')
);
$pdo = $pdo->connect('utf8', ['port' => getenv('MYSQL_PORT')]);
$db = new Mysql($pdo);

$monitor = $db->fetchRow('select count(id) from page_ouvrages where optidate is not null');
$number = (int)$monitor['count(id)'];

$monitor = $db->fetchRow('select count(distinct page) as pages from page_ouvrages where optidate is not null and isbn<>""');
$pageNb = (int)$monitor['pages'];

$newText = <<<EOF
<div style="background:#EBF6E9;border:2px solid grey;padding:10px;border-radius:10px;">
* 📗 [[Utilisateur:ZiziBot/features|Améliorations des références {ouvrage}]] (nov2019—mars2021)
 {{progression|##PAGEEDITED##|174569}}
<div style="text-align: center"><small>{{formatnum:##PAGEEDITED##}} articles WP traités sur {{formatnum:197639}} contenant un 
ISBN
</small></div>
<div style="text-align: center"><small>{{formatnum:##NUMBER##}} citations analysées sur {{formatnum:1064113}}
</small></div>
* 📘 Conversion liens bruts Google Books > {ouvrage} (juin—sept2000): {{Avancement|100}}
* 🌐 [[Utilisateur:ZiziBot/Complétion liens web|Conversion lien brut > {article}/{lien web}]] (juin2020-jan2021) : {{Avancement|22}}
</div>
EOF;

$newText = str_replace('##NUMBER##', $number, $newText);
$newText = str_replace('##PAGEEDITED##', $pageNb, $newText);

// Put content on wiki
$title = 'Utilisateur:ZiziBot/task';
$summary = 'bot : ⚙ mise à jour';

echo "Mise à jour avancement ?\n";
echo "sleep 20...\n";
sleep(20);

$wiki = ServiceFactory::wikiApi();
$page = new WikiPageAction($wiki, $title);

$success = $page->editPage($newText, new EditInfo($summary, true, true));
dump($success);

