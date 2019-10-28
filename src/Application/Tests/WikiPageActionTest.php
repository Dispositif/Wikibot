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
                'zzzzzzz {{Ouvrage|titre=bla}} zzzz {{en}} {{Ouvrage|titre=bla}} zerqsdfqs',
                '{{Ouvrage|titre=bla}}',
                '{{Ouvrage|lang=en|titre=BLO}}',
                'zzzzzzz {{Ouvrage|lang=en|titre=BLO}} zzzz {{Ouvrage|lang=en|titre=BLO}} zerqsdfqs',
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
