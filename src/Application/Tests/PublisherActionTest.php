<?php

namespace App\Application;

use PHPUnit\Framework\TestCase;

class PublisherActionTest extends TestCase
{

    public function testGetHTMLSource()
    {
        $this::markTestIncomplete();
    }

    public function testExtractLD_JSON()
    {
        $publisher = new PublisherAction('foo');

        $source = file_get_contents(__DIR__.'/exampleWebpage.html');
        $actualArray = $publisher->extractLD_JSON($source);
        $this::assertArrayHasKey('@type', $actualArray);
        $this::assertArrayHasKey('headline', $actualArray);
    }
}
