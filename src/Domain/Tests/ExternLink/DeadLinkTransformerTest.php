<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Tests\ExternLink;

use App\Domain\ExternLink\DeadLinkTransformer;
use App\Domain\ExternLink\ExternRefTransformerInterface;
use App\Domain\InfrastructurePorts\DeadlinkArchiverInterface;
use App\Domain\InfrastructurePorts\InternetDomainParserInterface;
use App\Domain\Models\Summary;
use App\Domain\Models\WebarchiveDTO;
use PHPUnit\Framework\TestCase;

class DeadLinkTransformerTest extends TestCase
{
    public function testFormatFromUrlWithNoArchiver()
    {
        $transformer = new DeadLinkTransformer();
        $now = new \DateTimeImmutable();

        $this::assertSame(
            sprintf('{{Lien brisé |url= bla |titre=bla |brisé le=%s}}', $now->format('d-m-Y')),
            $transformer->formatFromUrl('bla', $now)
        );
    }

    public function testFormatFromUrlWithArchiver()
    {
        $archiver = $this->createMock(DeadlinkArchiverInterface::class);
        $now = new \DateTimeImmutable();
        $webarchiveDTO = new WebarchiveDTO(
            'archiver test',
            'bla',
            'archive/bla',
            $now
        );
        $archiver->method('searchWebarchive')->willReturn($webarchiveDTO);
        $domainParser = $this->createMock(InternetDomainParserInterface::class);

        $externRefTransformer = $this->createMock(ExternRefTransformerInterface::class);
        $finalArchiverSerialized = '{{lien web via wikiwix}}';
        $externRefTransformer->method('process')->willReturn($finalArchiverSerialized);

        $transformer = new DeadLinkTransformer([$archiver], $domainParser, $externRefTransformer);
        $now = new \DateTimeImmutable();

        $this::assertSame(
            $finalArchiverSerialized,
            $transformer->formatFromUrl('bla', $now)
        );
    }

    public function testFormatFromUrlWithArchiverReturnNull()
    {
        $archiver = $this->createMock(DeadlinkArchiverInterface::class);
        $now = new \DateTimeImmutable();

        $archiver->method('searchWebarchive')->willReturn(null);

        $domainParser = $this->createMock(InternetDomainParserInterface::class);
        $externRefTransformer = $this->createMock(ExternRefTransformerInterface::class);

        $transformer = new DeadLinkTransformer([$archiver], $domainParser, $externRefTransformer);

        $this::assertStringContainsString(
            '{{Lien brisé |url= bla |titre=bla |brisé le=',
            $transformer->formatFromUrl('bla', $now)
        );
    }

    /**
     * Regression test for the 2026-08-06 incident : a dead link was replaced by a
     * bare web.archive.org URL because the archived snapshot turned out to be blank
     * (ExternRefTransformer::process() on the archive URL couldn't build a template
     * and returned the archive URL unchanged). The bare URL must never be surfaced :
     * it should fall back to {{Lien brisé}} instead.
     */
    public function testFormatFromUrlWithUnusableSnapshotFallsBackToLienBrise()
    {
        $archiver = $this->createMock(DeadlinkArchiverInterface::class);
        $now = new \DateTimeImmutable();
        $webarchiveDTO = new WebarchiveDTO('[[Internet Archive]]', 'bla', 'http://web.archive.org/web/2022/bla', $now);

        $archiver->method('searchWebarchiveCandidates')->willReturn([$webarchiveDTO]);

        $domainParser = $this->createMock(InternetDomainParserInterface::class);
        $externRefTransformer = $this->createMock(ExternRefTransformerInterface::class);
        // simulates ExternRefTransformer::process() unable to build a template on a blank snapshot
        $externRefTransformer->method('process')->willReturn('http://web.archive.org/web/2022/bla');

        $transformer = new DeadLinkTransformer([$archiver], $domainParser, $externRefTransformer);
        $result = $transformer->formatFromUrl('bla', $now);

        $this::assertStringStartsWith('{{Lien brisé', $result);
        $this::assertStringNotContainsString('web.archive.org', $result);
    }

    /**
     * When the first candidate snapshot is unusable, the next candidate (from the
     * same or a further archiver) must be tried before giving up on {{Lien brisé}}.
     */
    public function testFormatFromUrlTriesNextCandidateWhenFirstIsUnusable()
    {
        $now = new \DateTimeImmutable();
        $badDTO = new WebarchiveDTO('[[Internet Archive]]', 'bla', 'http://web.archive.org/web/2021/bla', $now);
        $goodDTO = new WebarchiveDTO('[[Internet Archive]]', 'bla', 'http://web.archive.org/web/2022/bla', $now);

        $archiver = $this->createMock(DeadlinkArchiverInterface::class);
        $archiver->method('searchWebarchiveCandidates')->willReturn([$badDTO, $goodDTO]);

        $domainParser = $this->createMock(InternetDomainParserInterface::class);
        $externRefTransformer = $this->createMock(ExternRefTransformerInterface::class);
        $externRefTransformer->method('process')->willReturnCallback(
            static fn(string $url) => str_contains($url, '2021')
                ? 'http://web.archive.org/web/2021/bla' // unusable : unchanged, no template
                : '{{lien web |url=http://web.archive.org/web/2022/bla}}'
        );

        $transformer = new DeadLinkTransformer([$archiver], $domainParser, $externRefTransformer);

        $this::assertSame(
            '{{lien web |url=http://web.archive.org/web/2022/bla}}',
            $transformer->formatFromUrl('bla', $now)
        );
    }

    /**
     * §9.8 : which archiver was used must be reported on the caller's own Summary
     * object, not re-derived later by string-matching the serialized result.
     */
    public function testFormatFromUrlRecordsWikiwixOnSummary()
    {
        $archiver = $this->createMock(DeadlinkArchiverInterface::class);
        $now = new \DateTimeImmutable();
        $webarchiveDTO = new WebarchiveDTO('[[Wikiwix]]', 'bla', 'https://archive.wikiwix.com/cache/bla', $now);
        $archiver->method('searchWebarchive')->willReturn($webarchiveDTO);

        $domainParser = $this->createMock(InternetDomainParserInterface::class);
        $externRefTransformer = $this->createMock(ExternRefTransformerInterface::class);
        $externRefTransformer->method('process')->willReturn('{{lien web |url=https://archive.wikiwix.com/cache/bla}}');

        $transformer = new DeadLinkTransformer([$archiver], $domainParser, $externRefTransformer);
        $summary = new Summary();
        $transformer->formatFromUrl('bla', $now, $summary);

        $this::assertSame(1, $summary->memo['wikiwix']);
        $this::assertArrayNotHasKey('wayback', $summary->memo);
    }

    public function testFormatFromUrlRecordsInternetArchiveOnSummary()
    {
        $archiver = $this->createMock(DeadlinkArchiverInterface::class);
        $now = new \DateTimeImmutable();
        $webarchiveDTO = new WebarchiveDTO('[[Internet Archive]]', 'bla', 'http://web.archive.org/web/2022/bla', $now);
        $archiver->method('searchWebarchive')->willReturn($webarchiveDTO);

        $domainParser = $this->createMock(InternetDomainParserInterface::class);
        $externRefTransformer = $this->createMock(ExternRefTransformerInterface::class);
        $externRefTransformer->method('process')->willReturn('{{lien web |url=http://web.archive.org/web/2022/bla}}');

        $transformer = new DeadLinkTransformer([$archiver], $domainParser, $externRefTransformer);
        $summary = new Summary();
        $transformer->formatFromUrl('bla', $now, $summary);

        $this::assertSame(1, $summary->memo['wayback']);
        $this::assertArrayNotHasKey('wikiwix', $summary->memo);
    }

    public function testFormatFromUrlRecordsLienBriseOnSummary()
    {
        $now = new \DateTimeImmutable();
        $summary = new Summary();

        (new DeadLinkTransformer())->formatFromUrl('bla', $now, $summary);

        $this::assertSame(1, $summary->memo['count lien brisé']);
    }

    public function testFormatFromUrlWorksWithoutSummary()
    {
        // $summary is optional : must not error when the caller doesn't care (e.g. tests, recursive archive-URL guard)
        $now = new \DateTimeImmutable();

        $this::assertStringStartsWith('{{Lien brisé', (new DeadLinkTransformer())->formatFromUrl('bla', $now));
    }

    public function testFormatFromUrlAddsHttpStatusNote()
    {
        $now = new \DateTimeImmutable();

        $this::assertSame(
            sprintf('{{Lien brisé |url= bla |titre=bla |brisé le=%s |note=HTTP 500}}', $now->format('d-m-Y')),
            (new DeadLinkTransformer())->formatFromUrl('bla', $now, null, 500)
        );
    }

    public function testFormatFromUrlWithoutHttpStatusOmitsNote()
    {
        $now = new \DateTimeImmutable();

        $this::assertStringNotContainsString(
            'note=',
            (new DeadLinkTransformer())->formatFromUrl('bla', $now)
        );
    }
}
