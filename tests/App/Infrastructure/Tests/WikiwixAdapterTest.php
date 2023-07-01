<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure\Tests;

use App\Application\Http\ExternHttpClient;
use App\Domain\Models\WebarchiveDTO;
use App\Infrastructure\WikiwixAdapter;
use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

class WikiwixAdapterTest extends TestCase
{
    public function testSearchWebarchive()
    {
        $timestampString = '912380400';
        $archiveUrl = 'https:\/\/archive.wikiwix.com\/cache\/19981130000000\/http:\/\/bla';
        $json = '{"status":200,"contenttype":"text\/html; charset=utf-8","timestamp":'
            . $timestampString . ',"datetime":"19981130000000","longformurl":"' . $archiveUrl . '"}';


        $body = $this::createMock(StreamInterface::class);
        $body->method('getContents')->willReturn($json);

        $response = $this::createMock(Response::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($body);

        $guzzleClient = $this::createMock(Client::class);
        $guzzleClient->method('request')
            ->with('GET', $this->anything(), $this->anything())
            ->willReturn($response);

        $httpClient = $this::createMock(ExternHttpClient::class);
        $httpClient->method('getClient')->willReturn($guzzleClient);

        $webArchiverAdapter = new WikiwixAdapter($httpClient);

        $this::assertEquals(
            new WebarchiveDTO(
                WikiwixAdapter::ARCHIVER_NAME,
                'bla',
                'https://archive.wikiwix.com/cache/19981130000000/http://bla',
                DateTimeImmutable::createFromFormat('U', $timestampString),
            ),
            $webArchiverAdapter->searchWebarchive("bla")
        );
    }

    public function test404Webarchive()
    {
        $response = $this::createMock(Response::class);
        $response->method('getStatusCode')->willReturn(404);

        $guzzleClient = $this::createMock(Client::class);
        $guzzleClient->method('request')
            ->with('GET', $this->anything(), $this->anything())
            ->willReturn($response);

        $httpClient = $this::createMock(ExternHttpClient::class);
        $httpClient->method('getClient')->willReturn($guzzleClient);

        $webArchiverAdapter = new WikiwixAdapter($httpClient);

        $this::assertEquals(
            null,
            $webArchiverAdapter->searchWebarchive("bla")
        );
    }
}
