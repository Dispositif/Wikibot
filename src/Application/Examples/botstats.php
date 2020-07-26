<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application\Examples;

use App\Application\WikiPageAction;
use App\Domain\Utils\DateUtil;
use App\Infrastructure\ServiceFactory;
use DateTime;
use Mediawiki\DataModel\EditInfo;
use Simplon\Mysql\Mysql;
use Simplon\Mysql\PDOConnector;

include __DIR__.'/../ZiziBot_Bootstrap.php'; //myBootstrap.php';

/**
 * oué c'est dégoutant et pas MVC...
 */


$pdo = new PDOConnector(
    getenv('MYSQL_HOST'), getenv('MYSQL_USER'), getenv('MYSQL_PASSWORD'), getenv('MYSQL_DATABASE')
);
$pdo = $pdo->connect('utf8', ['port' => getenv('MYSQL_PORT')]);
$db = new Mysql($pdo);
$data = [];

$monitor = $db->fetchRow('select count(id) from page_ouvrages where optidate is null and edited is null');
$data['not analyzed citation'] = (int)$monitor['count(id)'];

$monitor = $db->fetchRow('select count(id) from page_ouvrages where optidate is not null');
$data['analyzed citation'] = (int)$monitor['count(id)'];

$monitor = $db->fetchRow('select count(distinct page) as n from page_ouvrages where skip=1 and edited is null');
$data['skip pages'] = (int)$monitor['n'];

$monitor = $db->fetchRow('select count(distinct page) as n from page_ouvrages where edited is true');
$data['edited pages'] = (int)$monitor['n'];

$monitor = $db->fetchRow('select count(id) from page_ouvrages where optidate > SUBDATE(NOW(),1)');
$data['analyzed citation 24H'] = (int)$monitor['count(id)'];

$monitor = $db->fetchRow('select count(id) as n from page_ouvrages where edited > SUBDATE(NOW(),1)');
$data['edited citations 24H'] = (int)$monitor['n'];

$monitor = $db->fetchRow('select count(distinct page) as n from page_ouvrages where edited > SUBDATE(NOW(),1)');
$data['edited pages 24H'] = (int)$monitor['n'];

$monitor = $db->fetchRow('SELECT count(distinct A.page) FROM page_ouvrages A
                WHERE notcosmetic=1.
                AND NOT EXISTS
                    (SELECT B.* FROM page_ouvrages B
                    WHERE (
                        B.edited IS NOT NULL
                        OR B.optidate < "2019-11-20 14:00:00"
                        OR B.optidate IS NULL
                        OR B.opti=""
                        OR B.skip=1
                        )
                    AND A.page = B.page
                    )');
$data['waiting pages'] = (int)$monitor['count(distinct A.page)'];

$data['currentdate'] = DateUtil::dateEnglish2french((new DateTime())->format('j F Y \à H\:i').' (CEST)');

// modifs récentes sur ouvrage édité
$monitor = $db->fetchRowMany('select distinct page,edited,altered,version from page_ouvrages 
where edited is not null and edited>"2019-11-26 06:00:00" and altered>0 ORDER BY edited DESC LIMIT 60');


$monitWiki = '';
if(!empty($monitor)) {
    foreach ($monitor as $monit) {
        // &#37; = "%"
        $edited = new DateTime($monit['edited']);
        $monitWiki .= sprintf(
            "<tr><td>%s &#37;</td><td>%s</td><td>[https://fr.wikipedia.org/w/index.php?title=%s&action=history histo]</td><td>[[%s]]</td><td>%s</td></tr>\n",
            $monit['altered'] ?? '?',
            $edited->format('d-m-Y'),
            str_replace(' ','_', $monit['page']),
            $monit['page'] ?? '??',
            $monit['version']
        );
    }
}

dump($monitor);
dump($data);

//== Statistiques ==
//Depuis 12 novembre :
//
//* citations analysées : #analyzed citation#
//* citations en attente : #not analyzed citation#
//* articles ignorées : #skip pages#
//* articles édités : #edited pages#
//* articles édités dernières 24h : #edited pages 24H#
//* articles en attente édition : #waiting pages#

$wikiText = <<<wiki
<noinclude>{{Utilisateur:ZiziBot/menu}}</noinclude>
Dernières corrections humaines sur citations après passage du bot :

<table style="border:1px solid grey;padding:10px;margin:5px;">
<tr style="background:#DFEBDD;padding:5px;">
<td style="text-align: center;">modifié</td><td style="text-align: center;">édit bot</td><td>historique</td><td 
style="text-align: center;">titre 
de l'article</td><td style="text-align: center;">version<br>du bot</td>
</tr>
#monitor#
</table>

<small>Pourcentage de citations modifiées. Date du passage bot. Certaines corrections humaines ne sont pas listées (typo majuscule/minuscule, correction suite à signalement du bot).</small>
wiki;

$wikiText = str_replace('#monitor#', $monitWiki, $wikiText);
foreach($data as $key => $dat){
    $wikiText = str_replace('#'.$key.'#', $dat, $wikiText);
}

echo LC_ALL;

//Mise à jour : #currentdate#
//dump('stop');die;


echo "Edition ? \n";
echo "sleep 30...\n";
sleep(30);

$wiki = ServiceFactory::wikiApi();
$pageAction = new WikiPageAction($wiki, 'Utilisateur:ZiziBot/monitoring');

$success = $pageAction->editPage($wikiText, new EditInfo('bot : ⚙ mise à jour', false, false));
dump($success);

