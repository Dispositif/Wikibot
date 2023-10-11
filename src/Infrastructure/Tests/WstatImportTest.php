<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure\Tests;

use App\Infrastructure\WstatImport;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * @unused
 * Class WstatImportTest
 *
 * @package App\Infrastructure
 */
class WstatImportTest extends TestCase
{
    public function testGetData(): never
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

    public function testGetUrl(): never
    {
        // todo verify
        $this::markTestSkipped('long time test ??? HTTTP request ??? (delayed http request)');
        $wstat = new WstatImport(
            new Client(['timeout'=>10, 'headers' => ['User-Agent' => getenv('USER_AGENT')]]),
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
