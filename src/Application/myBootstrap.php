<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

error_reporting(E_ALL);
ini_set('display_errors', 'on');
//set_time_limit(60);
setlocale(LC_ALL, 'fr_FR.UTF-8');
date_default_timezone_set('Europe/Paris');

include __DIR__.'/../../vendor/autoload.php';

$dotFilename = __DIR__.'/../../.env';
if (file_exists($dotFilename)) {
    $dotEnv = new Dotenv();
    $dotEnv->load($dotFilename);
}
if (getenv('USER_AGENT')) {
    ini_set('user_agent', getenv('USER_AGENT'));
}
