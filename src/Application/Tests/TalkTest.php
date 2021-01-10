<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe/Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Application\Tests;

use App\Application\Bazar\TalkPage;
use PHPUnit\Framework\TestCase;

class TalkTest extends TestCase
{
    public $page;

    public function setUp(): void
    {
        $raw = <<<EOF
{{prout}}

== [[Marbourg]] un drôle de drapeau ? ==

Bonsoir,
Je viens de consulter la page Marbourg et j’ai été surpris de voir un drapeau ressemblant fortement à celui français. Une coïncidence ou du vandalisme ?
[[Utilisateur:Storberg|Storberg]] ([[Discussion utilisateur:Storberg|discuter]]) 9 janvier 2021 à 00:23 (CET)
: {{notif|Storberg}} Vandalisme, manifestement pas. Le fichier sur Commons indique [http://www.kommunalflaggen.de/cgi-bin/db.pl?eintrag:06534014: ceci] comme source. Reste à voir si cette source est fiable. Quoi qu'il en soit, je ne vois pas le nom du fichier dans le code de l'article. La seule hypothèse que j'ai est que ce drapeau est inclus via Wikidata. Si le drapeau est correct, ma foi pas de problème. Ce serait juste bien si on pouvait faire en sorte que le drapeau soit moins gros (en ajustant l'« upright »), car là il prend une place disproportionnée en raison du ratio hauteur/largeur (d'autant plus que le blason semble lui proportionnellement rétréci). [[Utilisateur:SenseiAC|SenseiAC]] ([[Discussion utilisateur:SenseiAC|discuter]]) 9 janvier 2021 à 03:42 (CET)
::Il y a une source plus détaillée [https://www.crwflags.com/fotw/flags/de-mr-mr.html ici] avec référence à un ouvrage de 1967. Je ne garantis pas du tout le sérieux mais c'est un peu plus vérifiable. --[[Utilisateur:Verkhana|Verkhana]] ([[Discussion utilisateur:Verkhana|discuter]]) 9 janvier 2021 à 05:39 (CET)
:
:: L’infobox {{m|Infobox subdivision administrative d'Allemagne}} inclue bien le drapeau de Wikidata en tout cas comme on peut vérifier dans le code. Le drapeau [[:d:Q3869#P41|y figure bien]]. Déclaration à enrichir si c’est bien un drapeau ou un drapeau historique, ou à déprécier si c’est une erreur sourçable :) — [[Utilisateur:TomT0m|TomT0m]] <sup>&#91;[[Discussion Utilisateur:TomT0m|bla]]&#93;</sup> 9 janvier 2021 à 11:36 (CET)
::: Ca a l'air bon après une très rapide recherche. Il y a aussi cela [[http://www.kommunalflaggen.de/cgi-bin/db.pl?eintrag:06534014:]] (mais quelle qualité ?). Sinon, WP:de mentionne également ce drapeau, mais pas en infobox. Je me suis baladé vite fait sur le site de la ville, pas trouvé d'info sur le drapeau mais vu une photo avec une communication officielle qui reprend un design assez proche du drapeau. La longueur fait partie intégrante du drapeau manifestement. Tout de bon [[Utilisateur:Triboulet sur une montagne|Triboulet sur une montagne]] ([[Discussion utilisateur:Triboulet sur une montagne|discuter]]) 9 janvier 2021 à 12:35 (CET)
::: J'ai écrit aux archives municipales, pour être fixé. Tout de bon [[Utilisateur:Triboulet sur une montagne|Triboulet sur une montagne]] ([[Discussion utilisateur:Triboulet sur une montagne|discuter]]) 9 janvier 2021 à 12:54 (CET)
:::: A la place de ''drapeau'', je mettrai ''bannière''. Sur le vrai drapeau les bandes sont horizontales avec les armoiries au milieu [https://www.flaggen-online.de/marburg-flagge-3598.html voir ici]--[[Utilisateur:Chromengel|Chromengel]] ([[Discussion utilisateur:Chromengel|discuter]]) 9 janvier 2021 à 13:16 (CET)

== Section 2 ==

Fubar yolo
Coucou--[[Utilisateur:bob|bob]] ([[Discussion utilisateur:bob|discuter]]) 1 janvier 2020 à 17:44 (CET)
:Oui. --[[Utilisateur:alice|alice]] ([[Discussion utilisateur:alice|discuter]]) 1 janvier 2020 à 18:00 (CET)
::non. --[[Utilisateur:tu|tu]] 1 janvier 2020 à 18:00 (CET)

== Troisième ==
Coucou. --[[Utilisateur:tu|tu]] 1 janvier 2020 à 18:00 (CET)

EOF;
        $this->page = new TalkPage('test', $raw);
    }


    public function testPage()
    {
        $this->page->rawParse();

        $this::markTestSkipped();
    }

    //    public function testParseNoSection()
    //    {
    //        $this->page = new TalkPage('test', "bla\n");
    //        $this->page->rawParse();
    //        $this::assertSame($this->page->getSections(), [ 0=> ['title'=>'', 'content'=>"bla\n"]]);
    //    }

}
