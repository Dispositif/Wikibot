<?php

use Symfony\Component\Dotenv\Dotenv;

$bootstrap = true;

error_reporting(E_ALL);
ini_set('display_errors', 'on');
set_time_limit(60);
setlocale(LC_ALL, 'fr_FR.UTF-8');
date_default_timezone_set('Europe/Paris');

include __DIR__.'/../../vendor/autoload.php';

$dotEnv = new Dotenv();
$dotEnv->load(__DIR__.'/../../.env');

ini_set("user_agent", $_ENV['USER_AGENT']);

//ini_set('xdebug.var_display_max_depth', '10');
//ini_set('xdebug.var_display_max_children', '256');
//ini_set('xdebug.var_display_max_data', '1024');


