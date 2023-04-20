<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\CLI;

use App\Application\WikiPageAction;
use App\Infrastructure\ServiceFactory;
use Exception;
use GuzzleHttp\Client;
use Mediawiki\DataModel\EditInfo;
use Throwable;

include __DIR__.'/../myBootstrap.php';

// get DOC content from github
$url = 'https://raw.githubusercontent.com/Dispositif/Wikibot/master/docs/fonctionnalite.wiki';
$response = (new Client())->get($url);
if (200 !== $response->getStatusCode()) {
    die('not 200 response');
}
try{
    $newText = $response->getBody()->getContents();
}catch (Throwable $e){
    dump($e);
    die;
}

// Put content on wiki
$title = 'Utilisateur:ZiziBot/features';
$summary = 'bot : Update from Github';

$wiki = ServiceFactory::getMediawikiFactory();

try {
    $page = new WikiPageAction($wiki, $title);
} catch (Exception $e) {
    echo "Erreur WikiPageAction\n";
    dump($e);
    die;
}
$success = $page->editPage($newText, new EditInfo($summary, true, true));
dump($success);

