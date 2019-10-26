<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class WstatImportTest extends TestCase
{
    public function testGetData()
    {
        $this::markTestSkipped('long time test (delayed http request');
        // 2 sets by json string
        $json
            = '["Alexandre S. Giffard|{{Ouvrage|pr\u00e9nom1=Karel,|isbn=2763772358|isbn2=9782763772356}}","Alexandre Saint-Yves d\'Alveydre|{{Ouvrage|nom1= Saunier|titre= La Synarchie}}", "<!-- + -->"]';
        $json2
            = '["Bob|{{Ouvrage|pr\u00e9nom1=Karel,|titre=Dice|isbn=2763772358|isbn2=9782763772356}}","Bob2|{{Ouvrage|nom1= Saunier|titre= La Synarchie}}", "<!-- + -->"]';

        // Mocking : stack of Responses or Exception for multiple http requests
        $mock = new MockHandler(
            [
                new Response(200, ['X-Foo' => 'Bar'], $json),
                new Response(200, [], $json2),
                new Response(200, [], $json2),
            ]
        );
        $handler = HandlerStack::create($mock);
        $client = new Client(['handler' => $handler]);

        $wstat = new WstatImport(
            $client, [
            'title' => 'Ouvrage',
            'query' => 'inclusions',
            'param' => 'isbn',
            'start' => 50000,
            'limit' => 10000, // no influence here
        ], 4 // so 2 loop
        );

        $data = $wstat->getData();
        // first request
        $this::assertEquals(
            [
                'title' => 'Alexandre S. Giffard',
                'template' => '{{Ouvrage|prénom1=Karel,|isbn=2763772358|isbn2=9782763772356}}',
            ],
            $data[0]
        );
        // second httpRequest
        $this::assertEquals(
            [
                'title' => 'Bob2',
                'template' => '{{Ouvrage|nom1= Saunier|titre= La Synarchie}}',
            ],
            $data[3]
        );
        // No 3nd httpRequest
        $this::assertFalse(isset($data[5]));
    }

    public function testGetUrl()
    {
        $wstat = new WstatImport(
            new Client(),
            [
                'title' => 'Ouvrage',
                'query' => 'inclusions',
                'param' => 'isbn',
                'start' => 50000,
                'limit' => 500,
            ], 10000
        );
        $this::assertEquals(
            'https://wstat.fr/template/index.php?title=Ouvrage&query=inclusions&param=isbn&start=50000&limit=500&format=json',
            $wstat->getUrl()
        );
    }
}
