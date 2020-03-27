<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application\Examples;

use App\Application\WikiPageAction;
use App\Infrastructure\ServiceFactory;
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
$title = 'Utilisateur:ZiziBotConfig/features';
$summary = 'bot : Update from Github';

$wiki = ServiceFactory::wikiApi();

try {
    $page = new WikiPageAction($wiki, $title);
} catch (\Exception $e) {
    echo "Erreur WikiPageAction\n";
    dump($e);
    die;
}
$success = $page->editPage($newText, new EditInfo($summary, true, true));
dump($success);

