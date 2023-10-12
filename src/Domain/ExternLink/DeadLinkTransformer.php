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
use DateTimeImmutable;
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

    /**
     * @param DeadlinkArchiverInterface[] $archivers
     */
    public function __construct(
        protected array                          $archivers = [],
        protected ?InternetDomainParserInterface $domainParser = null,
        protected ?ExternRefTransformerInterface $externRefTransformer = null,
        protected LoggerInterface                $log = new NullLogger()
    )
    {
    }

    public function formatFromUrl(string $url, DateTimeInterface $now = new DateTimeImmutable()): string
    {
        // choose randomly one archiver
        $oneArchiver = !empty($this->archivers) ? $this->archivers[array_rand($this->archivers)] : null;

        if ($oneArchiver instanceof DeadlinkArchiverInterface) {
            $webarchiveDTO = $oneArchiver->searchWebarchive($url);
            if ($webarchiveDTO instanceof WebarchiveDTO) {
                if ($webarchiveDTO->getArchiver() === '[[Wikiwix]]') {
                    $this->log->notice('🥝 Wikiwix found');
                }
                if ($webarchiveDTO->getArchiver() === '[[Internet Archive]]') {
                    $this->log->notice('🏛️ InternetArchive found');
                }
                $this->log->debug('archive url: ' . $webarchiveDTO->getArchiveUrl());

                return $this->generateLienWebFromArchive($webarchiveDTO);
            }
            $this->log->notice('web archive not found');
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
                'Archive ' . $this->generateTitleFromURLText($dto->getOriginalUrl()) . '<!-- titre à compléter -->',
                'via ' . $dto->getArchiver(),
                date('d-m-Y'),
                $dto->getArchiveDate() instanceof DateTimeInterface ? $dto->getArchiveDate()->format('d-m-Y') : ''
            );
        }

        return $externRefProcessOnArchive;
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
            $this->stripWebArchivePrefix($url),
            $this->generateTitleFromURLText($url),
            $now->format('d-m-Y')
        );
    }

    /**
     * Bug https://w.wiki/7kUm
     */
    private function stripWebArchivePrefix(string $url): string
    {
        $url = preg_replace('#^https?://web\.archive\.org/web/\d+/#', '', $url);
        $url = preg_replace('#^https?://archive\.is/\d+/#', '', $url);

        return preg_replace('#^https?://archive\.wikiwix\.com/cache/\d+/#', '', $url);
    }
}