<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe/Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\Examples;

use App\Application\CodexNotificationWorker;
use App\Infrastructure\ServiceFactory;

require_once __DIR__.'/../myBootstrap.php';

/**
 * Traitement des notifications du bot :
 * -> complète liste des notifications sur le wiki
 * -> ajoute articles à analyser (ouvrageComplete + externalLink)
 * Appelé par cron (genre toutes les 2h)
 */

echo date('Y-m-d H\:i:s')." Check notifications... \n";
new CodexNotificationWorker(ServiceFactory::getMediawikiApi(), 'Utilisateur:CodexBot/Notifications', []);


