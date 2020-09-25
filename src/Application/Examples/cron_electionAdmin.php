<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application\Examples;

use App\Application\VoteAdmin;
use App\Infrastructure\PageList;
use App\Infrastructure\ServiceFactory;

/**
 * Vote automatique sur élection administrateur
 */

require_once __DIR__.'/../myBootstrap.php'; // CODEX

$wiki = ServiceFactory::wikiApi();

$list = PageList::FromWikiCategory('Élection administrateur en cours');

foreach ($list->getPageTitles() as $title) {
    if (!preg_match('#Wikipédia:Admini#', $title)) {
        continue;
    }
    echo ">> élection $title \n";
    new VoteAdmin($title);
    sleep(60 * 10);
}
