<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

namespace App\Application\Examples;

use App\Application\GoogleBooksWorker;
use App\Application\WikiBotConfig;
use App\Infrastructure\CirrusSearch;
use App\Infrastructure\GoogleApiQuota;
use App\Infrastructure\ServiceFactory;


/**
 * Cherche des liens bruts Google Books (<ref> et liste * ) et les transforme en {ouvrage}
 * Consomme du quota journalier GB
 */

include __DIR__.'/../myBootstrap.php';

dump('Google quota : ', (new GoogleApiQuota())->getCount());

$wiki = ServiceFactory::wikiApi();
$bot = new WikiBotConfig();
$bot->taskName = "Amélioration bibliographique : lien Google Books ⇒ {ouvrage}";

// les "* https://..." en biblio et liens externes
// "https://books.google" insource:/\* https\:\/\/books\.google[^ ]+/
$list = new CirrusSearch(
    [
        'srsearch' => '"https://books.google" insource:/\*https\:\/\/books\.google/',
        'srnamespace' => '0',
        'srlimit' => '100',
        'srqiprofile' => 'popular_inclinks_pv',
        'srsort' => 'last_edit_desc',
    ]
);

new GoogleBooksWorker($bot, $wiki, $list);
