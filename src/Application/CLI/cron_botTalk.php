<?php

declare(strict_types=1);

namespace App\Application\CLI;

use App\Application\TalkBotConfig;
use App\Infrastructure\Monitor\ConsoleLogger;
use App\Infrastructure\ServiceFactory;

require_once __DIR__ . '/../myBootstrap.php';

$bot = new TalkBotConfig(ServiceFactory::getMediawikiFactory(), new ConsoleLogger());
$res = $bot->botTalk('Discussion utilisateur:CodexBot');
echo sprintf("> BotTalk result : %s\n", $res ? 'true' : 'false');
