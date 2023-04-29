<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application\Tests;

use App\Application\WikiPageAction;
use PHPUnit\Framework\TestCase;

class WikiPageActionTest extends TestCase
{
    /**
     * @dataProvider provideReplaceTemplateInText
     */
    public function testReplaceTemplateInText(string $text, string $origin, string $replace, string $expected)
    {
        $this::assertSame(
            $expected,
            WikiPageAction::replaceTemplateInText($text, $origin, $replace)
        );
    }

    public function provideReplaceTemplateInText()
    {
        return [
            [
                // Bug de suppression {{en}} sur citation étendue ==> SKIPPED
                // See https://fr.wikipedia.org/w/index
                //.php?title=Les_Sept_Samoura%C3%AFs&diff=prev&oldid=172046229&diffmode=source
                "* {{en}} {{Ouvrage
| titre=Seven Samurai
| éditeur=Loorrimer
| auteur= [[Donald Richie]] (ed.)
| lieu=Londres
| année=[[1970]]
| isbn=
| id=
}}",
                "{{Ouvrage
| titre=Seven Samurai
| éditeur=Loorrimer
| auteur= [[Donald Richie]] (ed.)
| lieu=Londres
| année=[[1970]]
| isbn=
| id=
}}",
                "{{Ouvrage
| auteur1=[[Donald Richie]] (ed.)
| titre=Seven Samurai
| lieu=Londres
| éditeur=Loorrimer
| année=1970
| isbn=
}}",
                // expected = identique à version WP
                "* {{en}} {{Ouvrage
| titre=Seven Samurai
| éditeur=Loorrimer
| auteur= [[Donald Richie]] (ed.)
| lieu=Londres
| année=[[1970]]
| isbn=
| id=
}}",

            ],
            [
                // {{en}} {{ouvrage}} et langue=en
                "{{en}} {{Ouvrage|titre=bla}}",
                '{{Ouvrage|titre=bla}}',
                '{{Ouvrage|langue=en|titre=BLO}}',
                "{{Ouvrage|langue=en|titre=BLO}}",
            ],
            [
                // {{en}} {{ouvrage}} et pas de 'langue'
                "{{en}} {{Ouvrage|titre=bla}}",
                '{{Ouvrage|titre=bla}}',
                '{{Ouvrage|titre=BLO}}',
                "{{en}} {{Ouvrage|titre=bla}}", // skiped => inchangé
            ],
            [
                // gestion {{fr}}
                "{{fr}} {{Ouvrage|auteur1=TOOOO Smollett|titre=Aventures de Roderick Random|lieu=Paris|éditeur=|année=1948|pages totales=542}}",
                "{{Ouvrage|auteur1=TOOOO Smollett|titre=Aventures de Roderick Random|lieu=Paris|éditeur=|année=1948|pages totales=542}}",
                "{{Ouvrage|auteur1=Tobias Smollett|titre=Aventures de Roderick Random|lieu=Paris|éditeur=|année=1948|pages totales=542}}",
                "{{fr}} {{Ouvrage|auteur1=Tobias Smollett|titre=Aventures de Roderick Random|lieu=Paris|éditeur=|année=1948|pages totales=542}}",
            ],
            [
                // saut de ligne {{en}} \n{{ouvrage}}
                "zzzzzzz {{Ouvrage|langue=|titre=bla}} zzzz {{de}}
{{Ouvrage|langue=|titre=bla}} zerqsdfqs",
                '{{Ouvrage|langue=|titre=bla}}',
                '{{Ouvrage|langue=|titre=BLO}}',
                "zzzzzzz {{Ouvrage|langue=de|titre=BLO}} zzzz {{Ouvrage|langue=de|titre=BLO}} zerqsdfqs",
            ],
            [
                'zzzzzzz {{ouvrage|titre=ping}} aaa {{Ouvrage|langue=|titre=bla}} zzzz {{de}} {{Ouvrage|langue=|titre=bla}} zerqsdfqs',
                '{{Ouvrage|langue=|titre=bla}}',
                '{{Ouvrage|langue=|titre=BLO}}',
                'zzzzzzz {{ouvrage|titre=ping}} aaa {{Ouvrage|langue=de|titre=BLO}} zzzz {{Ouvrage|langue=de|titre=BLO}} zerqsdfqs',
            ],
            [
                'zzzzzzz {{Ouvrage|titre=bla}} zzzz {{en}} {{Ouvrage|titre=bla}} zerqsdfqs',
                '{{Ouvrage|titre=bla}}',
                '{{Ouvrage|langue=en|titre=BLO}}',
                'zzzzzzz {{Ouvrage|langue=en|titre=BLO}} zzzz {{Ouvrage|langue=en|titre=BLO}} zerqsdfqs',
            ],
            [
                'zzzzzzz {{Ouvrage|titre=bla}} zzzz {{fr}} {{Ouvrage|titre=bla}} zerqsdfqs',
                '{{Ouvrage|titre=bla}}',
                '{{Ouvrage|titre=BLO}}',
                'zzzzzzz {{Ouvrage|titre=BLO}} zzzz {{fr}} {{Ouvrage|titre=BLO}} zerqsdfqs',
            ],
            [
                'zzzzzzz {{Ouvrage|titre=bla}} zzzz {{fr}} {{Ouvrage|titre=bla}} zerqsdfqs',
                '{{Ouvrage|titre=bla}}',
                '{{Ouvrage|langue=fr|titre=BLO}}',
                'zzzzzzz {{Ouvrage|langue=fr|titre=BLO}} zzzz {{fr}} {{Ouvrage|langue=fr|titre=BLO}} zerqsdfqs',
            ],
            [
                'zzzzzzz {{Ouvrage|langue=fr|titre=bla}} zzzz {{fr}} {{Ouvrage|langue=fr|titre=bla}} zerqsdfqs',
                '{{Ouvrage|langue=fr|titre=bla}}',
                '{{Ouvrage|langue=fr|titre=BLO}}',
                'zzzzzzz {{Ouvrage|langue=fr|titre=BLO}} zzzz {{fr}} {{Ouvrage|langue=fr|titre=BLO}} zerqsdfqs',
            ],
            //            [
            //                // TODO
            //                '{{en}}{{Ouvrage |auteur=Mary|titre=Labour}}',
            //                '{{Ouvrage |auteur=Mary|titre=Labour}}',
            //                '{{Ouvrage |auteur=Mary |titre=BETTER}}',
            //                '{{Ouvrage |langue=en |auteur=Mary |titre=BETTER}}',
            //            ],
        ];
    }

    //    public function testIntegration()
    //    {
    //        // Mediawiki namespace not PSR-4
    //        require_once __DIR__.'/../../../vendor/addwiki/mediawiki-api-base/tests/Integration/TestEnvironment.php';
    //        putenv('ADDWIKI_MW_API=http://localhost:8888/api.php');
    //
    ////        $api = MediawikiApi::newFromPage( TestEnvironment::newInstance()->getPageUrl() );
    ////        $this::assertInstanceOf( 'Mediawiki\Api\MediawikiApi', $api );
    //        $api = TestEnvironment::newInstance()->getApi();
    //        $page = new WikiPageAction(new MediawikiFactory($api), 'test');
    //        $this::assertInstanceOf('App\Application\WikiPageAction', $page);
    ////        dump($page);
    //    }
}
