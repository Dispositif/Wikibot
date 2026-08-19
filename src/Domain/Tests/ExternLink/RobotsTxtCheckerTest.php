<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Tests\ExternLink;

use App\Application\InfrastructurePorts\HttpClientInterface;
use App\Domain\ExternLink\RobotsTxtChecker;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

class RobotsTxtCheckerTest extends TestCase
{
    private function clientFor(MockHandler $mockHandler): HttpClientInterface
    {
        return new class ($mockHandler) implements HttpClientInterface {
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
        };
    }

    public function testNoRobotsTxtMeansAllowed(): void
    {
        $client = $this->clientFor(new MockHandler([new Response(404)]));
        $checker = new RobotsTxtChecker($client, 'CodexBot');

        $this::assertTrue($checker->isAllowed('https://example.com/page'));
    }

    /**
     * Crawl agreed with Wikiwix, whose archive host disallows everyone — see
     * RobotsTxtChecker::AGREED_CRAWL_DOMAINS. No robots.txt request is even made
     * (the MockHandler queue below stays untouched).
     */
    public function testAgreedCrawlDomainIgnoresDisallowAll(): void
    {
        $client = $this->clientFor(new MockHandler([new Response(200, [], "User-agent: *\nDisallow :/\n")]));
        $checker = new RobotsTxtChecker($client, 'CodexBot');

        $this::assertTrue($checker->isAllowed('https://archive.wikiwix.com/cache/index2.php?url=http://x.fr/a'));
        $this::assertTrue($checker->isAllowed('http://wikiwix.com/cache/?url=http://x.fr/a'));
    }

    public function testAgreedCrawlDomainDoesNotLeakToLookalikeHost(): void
    {
        $client = $this->clientFor(new MockHandler([new Response(200, [], "User-agent: *\nDisallow: /\n")]));
        $checker = new RobotsTxtChecker($client, 'CodexBot');

        $this::assertFalse($checker->isAllowed('https://notwikiwix.com/cache/?url=http://x.fr/a'));
    }

    public function testDisallowAllForWildcardBlocksUnmatchedBot(): void
    {
        $client = $this->clientFor(new MockHandler([new Response(200, [], "User-agent: *\nDisallow: /\n")]));
        $checker = new RobotsTxtChecker($client, 'CodexBot');

        $this::assertFalse($checker->isAllowed('https://example.com/page'));
    }

    public function testBotSpecificGroupTakesPrecedenceOverWildcard(): void
    {
        $body = "User-agent: CodexBot\nDisallow:\n\nUser-agent: *\nDisallow: /\n";
        $client = $this->clientFor(new MockHandler([new Response(200, [], $body)]));
        $checker = new RobotsTxtChecker($client, 'CodexBot');

        // empty Disallow value in our own group = no restriction, even though * blocks everything
        $this::assertTrue($checker->isAllowed('https://example.com/page'));
    }

    public function testPathPrefixDisallow(): void
    {
        $body = "User-agent: *\nDisallow: /private\n";
        $client = $this->clientFor(new MockHandler([new Response(200, [], $body)]));
        $checker = new RobotsTxtChecker($client, 'CodexBot');

        $this::assertFalse($checker->isAllowed('https://example.com/private/secret'));
        $this::assertTrue($checker->isAllowed('https://example.com/public'));
    }

    public function testLongestMatchWinsAllowOverridesDisallow(): void
    {
        $body = "User-agent: *\nDisallow: /private\nAllow: /private/public-sub\n";
        $client = $this->clientFor(new MockHandler([new Response(200, [], $body)]));
        $checker = new RobotsTxtChecker($client, 'CodexBot');

        $this::assertFalse($checker->isAllowed('https://example.com/private/secret'));
        $this::assertTrue($checker->isAllowed('https://example.com/private/public-sub/page'));
    }

    public function testWildcardAndEndAnchorPattern(): void
    {
        // classic real-world case only expressible with "*" + "$" : block any path
        // *ending* in .pdf, regardless of what comes before — plain prefix matching
        // (what a bare "Disallow: /foo" does) can't express this.
        $body = "User-agent: *\nDisallow: /*.pdf$\n";
        $client = $this->clientFor(new MockHandler([new Response(200, [], $body)]));
        $checker = new RobotsTxtChecker($client, 'CodexBot');

        $this::assertFalse($checker->isAllowed('https://example.com/files/report.pdf'));
        $this::assertTrue($checker->isAllowed('https://example.com/files/report.pdf.html'));
        $this::assertTrue($checker->isAllowed('https://example.com/files/index.html'));
    }

    public function testRobotsTxtFetchedOnceThenCachedPerHost(): void
    {
        // only ONE response queued : a 2nd real HTTP call on the same host would throw
        $client = $this->clientFor(new MockHandler([new Response(200, [], "User-agent: *\nDisallow: /a\n")]));
        $checker = new RobotsTxtChecker($client, 'CodexBot');

        $this::assertFalse($checker->isAllowed('https://example.com/a/1'));
        $this::assertTrue($checker->isAllowed('https://example.com/b'));
    }
}
