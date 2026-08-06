<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure\Tests;

use App\Application\InfrastructurePorts\HttpClientInterface;
use App\Domain\Models\WebarchiveDTO;
use App\Infrastructure\InternetArchiveAdapter;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class InternetArchiveAdapterTest extends TestCase
{
    private function cdxResponse(array $rows): Response
    {
        $header = ['urlkey', 'timestamp', 'original', 'mimetype', 'statuscode', 'digest', 'length'];

        return new Response(200, [], json_encode(array_merge([$header], $rows)));
    }

    public function testSearchWebarchiveCandidatesParsesRows()
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->method('get')->willReturn(
            $this->cdxResponse(
                [
                    ['org,example)/page', '20200101000000', 'https://example.com/page', 'text/html', '200', 'ABC', '1000'],
                    ['org,example)/page', '20220601000000', 'https://example.com/page', 'text/html', '200', 'DEF', '1200'],
                ]
            )
        );

        $adapter = new InternetArchiveAdapter($client);
        $candidates = $adapter->searchWebarchiveCandidates('https://example.com/page');

        $this::assertCount(2, $candidates);
        $this::assertContainsOnlyInstancesOf(WebarchiveDTO::class, $candidates);
        $this::assertSame(
            'https://web.archive.org/web/20220601000000/https://example.com/page',
            $candidates[0]->getArchiveUrl()
        );
    }

    public function testCandidatesAreOrderedByClosenessToDate()
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->method('get')->willReturn(
            $this->cdxResponse(
                [
                    ['k', '20180101000000', 'https://example.com/page', 'text/html', '200', 'A', '1'],
                    ['k', '20220615000000', 'https://example.com/page', 'text/html', '200', 'B', '1'],
                    ['k', '20220601000000', 'https://example.com/page', 'text/html', '200', 'C', '1'],
                ]
            )
        );

        $adapter = new InternetArchiveAdapter($client);
        $target = new \DateTimeImmutable('2022-06-05');
        $candidates = $adapter->searchWebarchiveCandidates('https://example.com/page', $target);

        $this::assertSame('20220601000000', $candidates[0]->getArchiveDate()->format('YmdHis'));
        $this::assertSame('20220615000000', $candidates[1]->getArchiveDate()->format('YmdHis'));
        $this::assertSame('20180101000000', $candidates[2]->getArchiveDate()->format('YmdHis'));
    }

    public function testWithoutDateMostRecentComesFirst()
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->method('get')->willReturn(
            $this->cdxResponse(
                [
                    ['k', '20180101000000', 'https://example.com/page', 'text/html', '200', 'A', '1'],
                    ['k', '20220615000000', 'https://example.com/page', 'text/html', '200', 'B', '1'],
                ]
            )
        );

        $adapter = new InternetArchiveAdapter($client);
        $candidates = $adapter->searchWebarchiveCandidates('https://example.com/page');

        $this::assertSame('20220615000000', $candidates[0]->getArchiveDate()->format('YmdHis'));
    }

    public function testEmptyCdxResponseReturnsNoCandidates()
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->method('get')->willReturn(new Response(200, [], ''));

        $adapter = new InternetArchiveAdapter($client);

        $this::assertSame([], $adapter->searchWebarchiveCandidates('https://example.com/page'));
        $this::assertNull($adapter->searchWebarchive('https://example.com/page'));
    }

    public function testNonJsonResponseReturnsNoCandidates()
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->method('get')->willReturn(new Response(200, [], 'not json at all'));

        $adapter = new InternetArchiveAdapter($client);

        $this::assertSame([], $adapter->searchWebarchiveCandidates('https://example.com/page'));
    }

    public function testNonOkHttpStatusReturnsNoCandidates()
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->method('get')->willReturn(new Response(503, [], ''));

        $adapter = new InternetArchiveAdapter($client);

        $this::assertSame([], $adapter->searchWebarchiveCandidates('https://example.com/page'));
    }
}
