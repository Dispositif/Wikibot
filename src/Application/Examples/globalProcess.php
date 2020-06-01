<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

namespace App\Application\Examples;

$filename = __DIR__.'/resources/global_process.json';

$saveData = function ($data) use ($filename) {
    $res = file_put_contents($filename, json_encode($data));
    if ($res === false) {
        throw new \Exception("impossible d'enregistrer $filename");
    }
};

while (true) {
    echo "\n*** GLOBAL PROCESS ***\n";
    $data = json_decode($filename, true);

    include __DIR__."/Monitor.php";
    include __DIR__."/botstats.php";
    echo "sleep 12h\n";
    sleep(60 * 60 * 12);
}
