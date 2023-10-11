<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink;

use App\Domain\InfrastructurePorts\DeadlinkArchiverInterface;
use App\Domain\InfrastructurePorts\InternetDomainParserInterface;
use App\Domain\Models\Summary;
use App\Domain\Models\WebarchiveDTO;
use App\Domain\Publisher\ExternMapper;
use App\Infrastructure\InternetDomainParser;
use App\Infrastructure\ServiceFactory;
use DateTimeInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Transform dead link url in {lien brisé} or import web archive URL
 */
class DeadLinkTransformer
{
    private const USE_TOR_FOR_ARCHIVE = false;
    private const DELAY_PARSE_ARCHIVE = 3;
    private const REPLACE_RAW_WIKIWIX_BY_LIENWEB = false;

    public function __construct(
        protected ?DeadlinkArchiverInterface     $archiver = null,
        protected ?InternetDomainParserInterface $domainParser = null,
        protected ?ExternRefTransformerInterface $externRefTransformer = null,
        protected LoggerInterface                $log = new NullLogger()
    )
    {
    }

    public function formatFromUrl(string $url, DateTimeInterface $now = new \DateTimeImmutable()): string
    {
        if ($this->archiver instanceof DeadlinkArchiverInterface) {
            $webarchive = $this->archiver->searchWebarchive($url);
            if ($webarchive instanceof WebarchiveDTO) {
                $this->log->notice('🥝wikiwix found');
                $this->log->debug('archive url: ' . $webarchive->getArchiveUrl());
                return $this->generateLienWebFromArchive($webarchive);
            }
            $this->log->notice('wikiwix not found');
        }

        return $this->generateLienBrise($url, $now);
    }

    private function generateLienWebFromArchive(WebarchiveDTO $dto): string
    {
        sleep(self::DELAY_PARSE_ARCHIVE);

        $externRefProcessOnArchive = $this->externRefProcessOnArchive($dto);

        // Wikiwix : "Sorry, this system is overloaded. Please come back in a minute."
        // manage content-type 'application/pdf' which is not parsed by ExternRefTransformer
        if (
            self::REPLACE_RAW_WIKIWIX_BY_LIENWEB
            && str_starts_with($externRefProcessOnArchive, 'https://archive.wikiwix.com/cache/')
        ) {
            $this->log->notice('Replace raw wikiwix by lien web');

            return sprintf(
                '{{Lien web |url= %s |titre=%s |site= %s |consulté le=%s |archive-date=%s}}',
                $dto->getArchiveUrl(),
                'Archive ' . $this->generateTitleFromURLText($dto->getOriginalUrl()).'<!-- titre à compléter -->',
                'via '.$dto->getArchiver(),
                date('d-m-Y'),
                $dto->getArchiveDate() instanceof DateTimeInterface ? $dto->getArchiveDate()->format('d-m-Y') : ''
            );
        }

        return $externRefProcessOnArchive;


        // OLD SOLUTION without a second GET request to wikiwix (todo make an switch option to the current class?)
//        return sprintf(
//            '{{Lien web |url= %s |titre=%s |site= %s |consulté le=%s |archive-date=%s}}',
//            $dto->getArchiveUrl(),
//            'Archive '. $this->generateTitleFromURLText($dto->getOriginalUrl()),
//            $dto->getArchiver(),
//            date('d-m-Y'),
//            $dto->getArchiveDate() ? $dto->getArchiveDate()->format('d-m-Y') : ''
//        );
    }

    /**
     * To extract the title+author+lang+… from the webarchive page.
     */
    private function externRefProcessOnArchive(WebarchiveDTO $dto): string
    {
        $summary = new Summary('test');
        if (!$this->externRefTransformer instanceof ExternRefTransformerInterface) {
            $this->externRefTransformer = new ExternRefTransformer(
                new ExternMapper($this->log),
                ServiceFactory::getHttpClient(self::USE_TOR_FOR_ARCHIVE),
                new InternetDomainParser(),
                $this->log,
                null
            ); // todo inverse dependency
        }

        $options = $this->domainParser instanceof InternetDomainParserInterface
            ? ['originalRegistrableDomain' => $this->domainParser->getRegistrableDomainFromURL($dto->getOriginalUrl())]
            : [];

        return $this->externRefTransformer->process($dto->getArchiveUrl(), $summary, $options);
    }

    protected function generateTitleFromURLText(string $url): string
    {
        $text = str_replace(['https://', 'http://', 'www.'], '', $url);
        if (strlen($text) > 30) {
            $text = substr($text, 0, 30) . '…';
        }

        return $text;
    }

    protected function generateLienBrise(string $url, DateTimeInterface $now): string
    {
        return sprintf(
            '{{Lien brisé |url= %s |titre=%s |brisé le=%s}}',
            $url,
            $this->generateTitleFromURLText($url),
            $now->format('d-m-Y')
        );
    }
}