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
        $finalArchiverSerialized = '{lien web via wikiwix}';
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
}
