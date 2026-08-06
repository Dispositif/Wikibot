<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Tests\ExternLink;

use App\Application\InfrastructurePorts\HttpClientInterface;
use App\Domain\ExternLink\ExternPageFactory;
use App\Domain\ExternLink\FetchErrorKind;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Thin HttpClientInterface wrapper around a real GuzzleHttp\Client backed by a
 * MockHandler, so ExternPageFactory::fetch()'s on_stats/exception handling is
 * exercised exactly as it runs against GuzzleClientAdapter/TorClientAdapter in
 * production (both are themselves thin wrappers over a real GuzzleHttp\Client).
 */
final class MockedHttpClient implements HttpClientInterface
{
    private Client $client;

    public function __construct(MockHandler $mockHandler)
    {
        $this->client = new Client(['handler' => HandlerStack::create($mockHandler)]);
    }

    public function get(string|UriInterface $uri, array $options = []): ResponseInterface
    {
        return $this->client->get($uri, $options);
    }

    public function request($method, $uri, array $options = []): ResponseInterface
    {
        return $this->client->request($method, $uri, $options);
    }
}

class ExternPageFactoryTest extends TestCase
{
    public function testSuccessfulFetch()
    {
        $client = new MockedHttpClient(new MockHandler([new Response(200, ['Content-Type' => 'text/html'], '<html><title>Bla</title></html>')]));
        $fetch = (new ExternPageFactory($client))->fetch('https://example.com/page');

        $this::assertTrue($fetch->isSuccess());
        $this::assertSame(200, $fetch->httpStatus);
        $this::assertStringContainsString('<title>Bla</title>', (string)$fetch->body);
    }

    public function test404ResponseIsClassifiedByStatusNotByExceptionMessage()
    {
        $client = new MockedHttpClient(new MockHandler([new Response(404, [], 'Page not found')]));
        $fetch = (new ExternPageFactory($client))->fetch('https://example.com/gone');

        $this::assertFalse($fetch->isSuccess());
        $this::assertSame(404, $fetch->httpStatus);
        $this::assertNull($fetch->errorKind);
    }

    public function test500ResponseIsClassifiedByStatus()
    {
        $client = new MockedHttpClient(new MockHandler([new Response(500, [], 'oops')]));
        $fetch = (new ExternPageFactory($client))->fetch('https://example.com/broken');

        $this::assertSame(500, $fetch->httpStatus);
    }

    public function testPdfContentTypeIsNotFetchedAsBody()
    {
        $client = new MockedHttpClient(new MockHandler([new Response(200, ['Content-Type' => 'application/pdf'], '%PDF-1.4...')]));
        $fetch = (new ExternPageFactory($client))->fetch('https://example.com/doc.pdf');

        $this::assertFalse($fetch->isSuccess());
        $this::assertNull($fetch->body);
        $this::assertSame(200, $fetch->httpStatus);
    }

    public function testDnsFailureIsClassified()
    {
        $mockHandler = new MockHandler([
            new ConnectException(
                'cURL error 6: Could not resolve host: nowhere.invalid',
                new Request('GET', 'https://nowhere.invalid/')
            ),
        ]);
        $client = new MockedHttpClient($mockHandler);
        $fetch = (new ExternPageFactory($client))->fetch('https://nowhere.invalid/');

        $this::assertFalse($fetch->isSuccess());
        $this::assertNull($fetch->httpStatus);
        $this::assertSame(FetchErrorKind::DnsResolutionFailed, $fetch->errorKind);
    }

    public function testMalformedUrlThrows()
    {
        $client = new MockedHttpClient(new MockHandler([]));

        $this->expectException(\DomainException::class);
        (new ExternPageFactory($client))->fetch('not-a-url');
    }
}
