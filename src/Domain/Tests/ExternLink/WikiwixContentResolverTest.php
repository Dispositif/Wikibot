<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Tests\ExternLink;

use App\Application\InfrastructurePorts\HttpClientInterface;
use App\Domain\ExternLink\WikiwixContentResolver;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class WikiwixContentResolverTest extends TestCase
{
    private const CACHE_URL = 'http://wikiwix.com/cache/?url=http://www.ign.fr/affiche_rubrique.asp?rbr_id=1087%26CommuneId=11364';
    /** a real token.php answer (2026-08-19) */
    private const TOKEN = '[17686408795,19206986193,36112402586]';

    public function testHandshakeProducesThePageUrl(): void
    {
        $resolver = $this->resolver(new Response(200, [], self::TOKEN));

        self::assertSame(
        // a = (l + d/2)/2 ; b = c - a - ((d/2) - a)/3*2 — as the SPA computes them
            'https://archive.wikiwix.com/cache/page.php?a=17871305044&b=1212416983'
            . '&url=http%3A%2F%2Fwww.ign.fr%2Faffiche_rubrique.asp%3Frbr_id%3D1087%26CommuneId%3D11364',
            $resolver->resolveContentUrl(self::CACHE_URL)
        );
    }

    /**
     * "%26" in the cache URL separates the ORIGINAL URL's query params : the handshake
     * must send the decoded URL, like URLSearchParams.get('url') hands it to the SPA.
     */
    public function testOriginalUrlIsSentDecoded(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->expects(self::once())
            ->method('get')
            ->with(self::stringContains('url=http%3A%2F%2Fwww.ign.fr%2Faffiche_rubrique.asp%3Frbr_id%3D1087%26CommuneId%3D11364'))
            ->willReturn(new Response(200, [], self::TOKEN));

        (new WikiwixContentResolver($client, handshakeDelay: 0))->resolveContentUrl(self::CACHE_URL);
    }

    public function testLongformCacheUrlAlsoResolved(): void
    {
        $resolver = $this->resolver(new Response(200, [], self::TOKEN));

        self::assertStringContainsString(
            'url=http%3A%2F%2Fcasamaures.org%2Fkeskispas.php',
            (string)$resolver->resolveContentUrl('https://archive.wikiwix.com/cache/19981130000000/http://casamaures.org/keskispas.php')
        );
    }

    public function testNoOriginalUrlToResolve(): void
    {
        $resolver = $this->resolver(new Response(200, [], self::TOKEN));

        self::assertNull($resolver->resolveContentUrl('https://archive.wikiwix.com/'));
    }

    /** protocol changed / service down : caller falls back to the plain cache URL */
    public function testNonJsonAnswerGivesNull(): void
    {
        $resolver = $this->resolver(new Response(200, [], '<!doctype html><title>Wikiwix Archives</title>'));

        self::assertNull($resolver->resolveContentUrl(self::CACHE_URL));
    }

    public function testTruncatedTripleGivesNull(): void
    {
        $resolver = $this->resolver(new Response(200, [], '[123,456]'));

        self::assertNull($resolver->resolveContentUrl(self::CACHE_URL));
    }

    public function testHttpErrorGivesNull(): void
    {
        $resolver = $this->resolver(new Response(503, [], ''));

        self::assertNull($resolver->resolveContentUrl(self::CACHE_URL));
    }

    private function resolver(Response $tokenResponse): WikiwixContentResolver
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->method('get')->willReturn($tokenResponse);

        return new WikiwixContentResolver($client, handshakeDelay: 0);
    }
}
