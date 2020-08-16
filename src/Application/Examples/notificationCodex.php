<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);

namespace App\Application\Examples;

use App\Application\NotificationWorker;

require_once __DIR__.'/../myBootstrap.php';

/**
 * Traitement des notifications du bot :
 * -> complète liste des notifications sur le wiki
 * -> ajoute articles à analyser (ouvrageComplete + externalLink)
 */

echo date('Y-m-d H\:i')." Check notifications... \n";
new NotificationWorker();
echo "sleep 4h\n";
sleep(3600 * 4);


