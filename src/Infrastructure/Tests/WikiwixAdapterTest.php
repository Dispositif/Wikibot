<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure\Tests;

use App\Application\InfrastructurePorts\HttpClientInterface;
use App\Domain\ExternLink\WikiwixContentResolver;
use App\Domain\Models\WebarchiveDTO;
use App\Infrastructure\WikiwixAdapter;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * The lookup is now "run the viewer handshake and read what page.php answers", so these
 * cover the two answers that decide it — see WikiwixAdapter's docblock.
 */
class WikiwixAdapterTest extends TestCase
{
    private const URL = 'http://www.ign.fr/affiche_rubrique.asp';
    /** a real token.php answer (2026-08-19) */
    private const TOKEN = '[17686408795,19206986193,36112402586]';

    private const ARCHIVED_PAGE = '<html lang="FR"><head><title>IGN - Données géographiques sur ma commune</title>'
    . '</head><body>Altitude de la commune…</body></html>';

    /** Wikiwix's soft 404 for an URL it never archived : HTTP 200 over an <h1>404</h1> */
    private const UNKNOWN_PAGE = '<html><head><title>Wikiwix Archive - Unknown page</title></head>'
    . '<body><h1>404</h1><p>We are really sorry.</p></body></html>';

    private const VIEWER_SHELL = '<!doctype html><html lang="en"><head><title>Wikiwix Archives</title></head>'
    . '<body><noscript>You need to enable JavaScript to run this app.</noscript><div id="root"></div></body></html>';

    public function testSnapshotFoundGivesTheViewerUrl(): void
    {
        $archive = $this->adapter(new Response(200, [], self::TOKEN), new Response(200, [], self::ARCHIVED_PAGE))
            ->searchWebarchive(self::URL);

        self::assertInstanceOf(WebarchiveDTO::class, $archive);
        self::assertSame('[[Wikiwix]]', $archive->getArchiver());
        self::assertSame(self::URL, $archive->getOriginalUrl());
        self::assertSame(
            'https://archive.wikiwix.com/cache/index2.php?url=http%3A%2F%2Fwww.ign.fr%2Faffiche_rubrique.asp',
            $archive->getArchiveUrl()
        );
        // no date : the SPA exposes none any more
        self::assertNull($archive->getArchiveDate());
    }

    public function testCandidatesWrapTheSingleSnapshot(): void
    {
        $candidates = $this->adapter(new Response(200, [], self::TOKEN), new Response(200, [], self::ARCHIVED_PAGE))
            ->searchWebarchiveCandidates(self::URL);

        self::assertCount(1, $candidates);
        self::assertContainsOnlyInstancesOf(WebarchiveDTO::class, $candidates);
    }

    public function testUnknownPageMeansNoSnapshot(): void
    {
        self::assertNull(
            $this->adapter(new Response(200, [], self::TOKEN), new Response(200, [], self::UNKNOWN_PAGE))
                ->searchWebarchive(self::URL)
        );
    }

    /** handshake bypassed/failed : the viewer shell is not archived content either */
    public function testViewerShellMeansNoSnapshot(): void
    {
        self::assertNull(
            $this->adapter(new Response(200, [], self::TOKEN), new Response(200, [], self::VIEWER_SHELL))
                ->searchWebarchive(self::URL)
        );
    }

    public function testHandshakeFailureGivesNoSnapshot(): void
    {
        self::assertNull(
        // token.php answering HTML instead of JSON = the protocol changed under us
            $this->adapter(new Response(200, [], '<!doctype html><title>Wikiwix Archives</title>'), new Response(200, [], self::ARCHIVED_PAGE))
                ->searchWebarchive(self::URL)
        );
    }

    public function testPageHttpErrorGivesNoSnapshot(): void
    {
        self::assertNull(
            $this->adapter(new Response(200, [], self::TOKEN), new Response(403, [], 'Forbidden'))
                ->searchWebarchive(self::URL)
        );
    }

    private function adapter(Response $tokenResponse, Response $pageResponse): WikiwixAdapter
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->method('get')->willReturnOnConsecutiveCalls($tokenResponse, $pageResponse);

        return new WikiwixAdapter($client, contentResolver: new WikiwixContentResolver($client, handshakeDelay: 0));
    }
}
