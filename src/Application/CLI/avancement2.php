<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\CLI;

use App\Application\WikiPageAction;
use App\Infrastructure\ServiceFactory;
use Mediawiki\DataModel\EditInfo;

include __DIR__ . '/../myBootstrap.php'; //myBootstrap.php';

$title = 'Utilisateur:ZiziBot/task';
$summary = 'bot : ⚙ mise à jour';


$newText = <<<EOF
<noinclude>{{Mise à jour bot|CodexBot|période=certaines nuits de pleine lune|nocat=1}}</noinclude>
<div style="background:#EBF6E9;border:2px solid grey;padding:10px;border-radius:10px;">
<div style="float:right;color:darkred;padding:1px;text-align:center;">'''CodexBot'''<br />[[File:Robot icon.svg|50px|link=Utilisateur:CodexBot|alt=dessin robot]]<br><small style="color:darkgrey">{{REVISIONDAY2}}-{{REVISIONMONTH}}-{{REVISIONYEAR}}</small></div>
* '''[ralenti]''' ☆📗 Surveillance {Ouvrage} sur articles de qualité, BA, potentiels AdQ/BA. <small>[[Spécial:Diff/203180020|exemple]]</small>
* '''[en cours]''' 📘 Surveillance RC : liens bruts Google Books → {Ouvrage}. <small>[[Spécial:Diff/203173830|exemple]]</small>
* '''[en cours]''' 🌐 [[Utilisateur:ZiziBot/Complétion liens web|Conversion liens bruts http://…]] → {Article}, {Lien web} ou {Lien brisé}. <small>[[Spécial:Diff/203202574|exemple]]</small>
* '''[ralenti]''' 🐭 Surveillance des RC pour liens externes bruts.
* '''[en cours]''' 📗 [[Utilisateur:ZiziBot/features|Améliorations des références {{m-|ouvrage}}]].
</div>
EOF;

echo "Mise à jour avancement ?\n";
echo strip_tags($newText)."\n";
echo "\nsleep 20...\n";
sleep(20);

$wiki = ServiceFactory::getMediawikiFactory();
$page = new WikiPageAction($wiki, $title);

$success = $page->editPage($newText, new EditInfo($summary, false, true));

echo sprintf("Edit page %s : %s\n", $title, $success ? 'OK' : 'FAIL');

