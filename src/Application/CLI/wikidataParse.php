<?php

namespace App\Application\CLI;

use App\Application\IndexController;

require_once __DIR__.'/../myBootstrap.php';

//{
//    "item": "http://www.wikidata.org/entity/Q73820",
//    "itemLabel": "MIT Press",
//    "itemAltLabel": "The M.I.T. Press, The MIT Press",
//    "wp": "MIT_Press"
//  },


$data = json_decode(file_get_contents(__DIR__.'/../resources/wikidata_editeurs.json'), true, 512, JSON_THROW_ON_ERROR);

$res = [];
$i = 0;
foreach ($data as $dat) {
    $names = [$dat['itemLabel']];
    $alts = [];
    if (isset($dat['itemAltLabel'])) {
        $alts = explode(', ', (string) $dat['itemAltLabel']);
    }
    $names = [...$names, ...$alts];
    $dat['wp'] = trim(str_replace('_', ' ', (string) $dat['wp']));
    foreach ($names as $name) {
        $res[$name] = urldecode($dat['wp']);
    }
}

file_put_contents(__DIR__."/data_editors_wiki.json", json_encode($res, JSON_THROW_ON_ERROR));
chmod(__DIR__."/data_editors_wiki.json", 0666);

