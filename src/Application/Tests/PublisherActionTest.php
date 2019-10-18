<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 (c) Philippe M. <dispositif@gmail.com>
 * For the full copyright and license information, please view the LICENSE file
 */

declare(strict_types=1);

namespace App\Application;

use PHPUnit\Framework\TestCase;

class PublisherActionTest extends TestCase
{
    public function testGetHTMLSource()
    {
        $this::markTestIncomplete();
    }

    public function testExtractLdJson()
    {
        $publisher = new PublisherAction('foo');

        $source = file_get_contents(__DIR__.'/exampleWebpage.html');
        $actualArray = $publisher->extractLdJson($source);
        $this::assertArrayHasKey('@type', $actualArray);
        $this::assertArrayHasKey('headline', $actualArray);
    }
}
