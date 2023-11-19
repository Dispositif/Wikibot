<?php

declare(strict_types=1);

namespace App\Application\CLI;

use App\Application\BotDayAction;
use App\Application\WikiBotConfig;
use App\Infrastructure\Monitor\ConsoleLogger;
use App\Infrastructure\ServiceFactory;

require_once __DIR__ . '/../myBootstrap.php';
$bot = new WikiBotConfig(ServiceFactory::getMediawikiFactory(), new ConsoleLogger());

$titles = [
    'Utilisateur:CodexBot',
    'Utilisateur:CodexBot2',
    'Utilisateur:Irønie',
    'Utilisateur:ZiziBot',
    'Wikipédia:Le_Bistro/23_juin_2024',
    'Wikipédia:Le_Bistro/23_juin_2025',
    'Wikipédia:Le_Bistro/23_juin_2026',
    'Wikipédia:Bot',
    'Projet:Correction des liens externes',
    'Projet:Maintenance',
    'Projet:Correction syntaxique',
    'Wikipédia:Spam',
    'Wikipédia:Bot/Requêtes/2024/06',
    'Wikipédia:Bot/Requêtes/2025/06',
    'Aide:Message de bienvenue',
//    'Utilisateur:SyntaxTerrorBot',
//    'Utilisateur:OrlodrimBot',
//    'Utilisateur:Loveless',
//    'Utilisateur:Escargot mécanique',
//    'Utilisateur:Salebot',
];

// add banner {{bot day}} to the top of the pages during june
new BotDayAction($bot, $titles);
