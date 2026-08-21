<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2026 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Tests\ExternLink;

use App\Domain\ExternLink\ArchiveUrlParser;
use App\Domain\Models\WebarchiveDTO;
use PHPUnit\Framework\TestCase;

/**
 * Pure URL parsing, no collaborators : the DTO produced here is what
 * DeadLinkTransformer::tryKnownArchive() feeds to the shared candidate path.
 */
class ArchiveUrlParserTest extends TestCase
{
    private const ORIGINAL = 'http://edition.channel5belize.com/archives/92071';

    public function testParsesWaybackUrlWithFullTimestamp()
    {
        $archive = 'https://web.archive.org/web/20131104044228/' . self::ORIGINAL;

        $dto = ArchiveUrlParser::parse($archive, self::ORIGINAL);

        self::assertInstanceOf(WebarchiveDTO::class, $dto);
        self::assertSame('[[Internet Archive]]', $dto->getArchiver());
        self::assertSame($archive, $dto->getArchiveUrl());
        // The citation's own dead URL, never the archive : it drives the
        // "site via [[Internet Archive]]" label downstream.
        self::assertSame(self::ORIGINAL, $dto->getOriginalUrl());
        self::assertSame('2013-11-04', $dto->getArchiveDate()?->format('Y-m-d'));
    }

    /**
     * "if_"/"id_"/"js_" between timestamp and URL : a Wayback rendering modifier, part of
     * neither value.
     */
    public function testParsesWaybackUrlWithRenderingModifier()
    {
        $dto = ArchiveUrlParser::parse(
            'https://web.archive.org/web/20131104044228if_/' . self::ORIGINAL,
            self::ORIGINAL
        );

        self::assertInstanceOf(WebarchiveDTO::class, $dto);
        self::assertSame('2013-11-04', $dto->getArchiveDate()?->format('Y-m-d'));
    }

    /**
     * A partial stamp carries no usable day : the citation's own 'archive-date' takes over.
     */
    public function testFallsBackToArchiveDateParamWhenTimestampIsPartial()
    {
        $dto = ArchiveUrlParser::parse(
            'https://web.archive.org/web/2013/' . self::ORIGINAL,
            self::ORIGINAL,
            '2013-11-04'
        );

        self::assertInstanceOf(WebarchiveDTO::class, $dto);
        self::assertSame('2013-11-04', $dto->getArchiveDate()?->format('Y-m-d'));
    }

    /**
     * createFromFormat() would silently roll "20131340" into a valid date -- rejected, and
     * with no 'archive-date' to fall back on the DTO simply carries no date.
     */
    public function testRejectsImpossibleTimestampRatherThanRollingItOver()
    {
        $dto = ArchiveUrlParser::parse(
            'https://web.archive.org/web/20131340044228/' . self::ORIGINAL,
            self::ORIGINAL
        );

        self::assertInstanceOf(WebarchiveDTO::class, $dto);
        self::assertNull($dto->getArchiveDate());
    }

    public function testParsesWikiwixUrlAndCanonicalizesToHttps()
    {
        $dto = ArchiveUrlParser::parse(
            'http://wikiwix.com/cache/?url=' . self::ORIGINAL,
            self::ORIGINAL,
            '2013-11-04'
        );

        self::assertInstanceOf(WebarchiveDTO::class, $dto);
        self::assertSame('[[Wikiwix]]', $dto->getArchiver());
        self::assertStringStartsWith('https://archive.wikiwix.com/cache/', $dto->getArchiveUrl());
        // Wikiwix's "/cache/<n>/" segment is an internal id, so the date can only come
        // from the citation's own 'archive-date'.
        self::assertSame('2013-11-04', $dto->getArchiveDate()?->format('Y-m-d'));
    }

    /**
     * Blacklisted on frwiki since 2026-02-23 : recognized, never reused as content.
     */
    public function testReturnsNullOnArchiveToday()
    {
        self::assertNull(ArchiveUrlParser::parse('https://archive.is/abcde', self::ORIGINAL));
        self::assertNull(ArchiveUrlParser::parse('https://archive.today/2013/' . self::ORIGINAL, self::ORIGINAL));
    }

    public function testReturnsNullOnUnknownArchiverSoCallerSearchesNormally()
    {
        self::assertNull(ArchiveUrlParser::parse('https://example.com/mirror/page', self::ORIGINAL));
    }

    public function testReturnsNullOnEmptyInput()
    {
        self::assertNull(ArchiveUrlParser::parse('', self::ORIGINAL));
        self::assertNull(ArchiveUrlParser::parse('https://web.archive.org/web/20131104044228/x', ''));
    }
}
