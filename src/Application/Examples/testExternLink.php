<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);

namespace App\Application\Examples;

use App\Application\ExternRefTransformer;
use App\Infrastructure\Logger;
use Codedungeon\PHPCliColors\Color;

require_once __DIR__.'/../myBootstrap.php';

$url = $argv[1];
if (empty($url)) {
    die("php testPress.php 'http://...'\n");
}

echo Color::BG_LIGHT_RED.$url.Color::NORMAL."\n";

$log = new Logger();
$log->debug = true;
$log->verbose = true;
$trans = new ExternRefTransformer($log);
$trans->skipUnauthorised = false;
$result = $trans->process($url);

echo $result."\n";





