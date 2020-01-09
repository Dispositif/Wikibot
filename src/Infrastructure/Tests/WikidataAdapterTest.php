<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Infrastructure\Tests;

use App\Infrastructure\WikidataAdapter;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * Class WikidataAdapterTest
 *
 * @package App\Infrastructure\Tests
 */
class WikidataAdapterTest extends TestCase
{

    public function testGetUrl()
    {
        $jsonFixture = file_get_contents(__DIR__.'/fixture_WD_ISNI.json');

        $mock = new MockHandler(
            [
                new Response(200, ['X-Foo' => 'Bar'], $jsonFixture),
            ]
        );
        $handler = HandlerStack::create($mock);
        $clientMocked = new Client(['handler' => $handler]);

        $wikidata = new WikidataAdapter($clientMocked);
        $actual = $wikidata->searchByISNI('0000 0001 2137 320X');

        $this::assertSame(
            'https://fr.wikipedia.org/wiki/Michel_Houellebecq',
            $actual['article']['value']
        );

        $this::assertSame(
            '66522427',
            $actual['viaf']['value']
        );
    }
}
