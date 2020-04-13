<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Application;

use PHPUnit\Framework\TestCase;

class ExternActionTest extends TestCase
{
    public function testGetHTMLSource()
    {
        $this::markTestIncomplete();
    }

    // TODO refac avec public extractDataFromHtml si Client hors constructor
    public function testExtractLdJson()
    {
        $this::markTestSkipped('after refactoring');

        $publisher = new ExternalAction('foo');

        $source = file_get_contents(__DIR__.'/exampleExternPage.html');
        $data = $publisher->extractDataFromHTML($source);
        $jsonLd = $data['JSON-LD'];
        $this::assertArrayHasKey('@type', $jsonLd);
        $this::assertArrayHasKey('headline', $jsonLd);
    }
}
