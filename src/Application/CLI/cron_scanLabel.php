<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\CLI;

use App\Application\OuvrageScan\ScanWiki2DB;
use App\Application\WikiBotConfig;
use App\Infrastructure\ConsoleLogger;
use App\Infrastructure\DbAdapter;
use App\Infrastructure\PageList;
use App\Infrastructure\ServiceFactory;


include __DIR__.'/../myBootstrap.php';

/**
 * From a titles list, scan the wiki and add the {ouvrage} citations into the DB
 */

$wiki = ServiceFactory::getMediawikiFactory();

// Catégories : Article potentiellement bon, Article potentiellement de qualité
echo "Catégories : Article potentiellement bon, Article potentiellement de qualité\n";
$ba = PageList::FromWikiCategory('Article potentiellement de qualité');
$adq = PageList::FromWikiCategory('Article potentiellement bon');
$list = new PageList(array_merge($ba->getPageTitles(), $adq->getPageTitles()));

new ScanWiki2DB($wiki, new DbAdapter(), new WikiBotConfig($wiki, new ConsoleLogger()), $list, 20);
