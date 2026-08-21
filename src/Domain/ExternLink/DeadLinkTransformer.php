<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink;

use App\Domain\ExternLink\Raw\Hints\FrenchDate;
use App\Domain\InfrastructurePorts\DeadlinkArchiverInterface;
use App\Domain\InfrastructurePorts\InternetDomainParserInterface;
use App\Domain\Models\Summary;
use App\Domain\Models\WebarchiveDTO;
use App\Domain\Publisher\ExternMapper;
use App\Infrastructure\InternetDomainParser;
use App\Infrastructure\Monitor\NullLogger;
use App\Infrastructure\ServiceFactory;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Log\LoggerInterface;

/**
 * Transform dead link url in {lien brisé} or import web archive URL
 */
class DeadLinkTransformer
{
    private const USE_TOR_FOR_ARCHIVE = false;
    private const DELAY_PARSE_ARCHIVE = 3;
    private const MAX_CANDIDATES_PER_ARCHIVER = 5;

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

    /**
     * $summary (the real, caller-owned Summary — distinct from the throwaway one used
     * internally for the recursive archive-page fetch, see externRefProcessOnArchive())
     * is where the outcome gets recorded, so ExternRefWorker's edit summary/botflag
     * logic reads it directly instead of re-deriving it by string-matching the
     * serialized result (see docs/audit-gestion-erreurs-crawl-2026-08.md §9.8).
     *
     * $knownArchive : an archive the CITATION ITSELF already carried ('archive-url'),
     * handed over by the caller through ArchiveUrlParser (2026-08-20). Tried as candidate
     * #0 before any archiver is queried — see tryKnownArchive(). Pass it as a named
     * argument ; the positional tail here is already long and every existing call site
     * uses named arguments past $url.
     */
    public function formatFromUrl(string $url, DateTimeInterface $now = new DateTimeImmutable(), ?Summary $summary = null, ?int $httpStatus = null, ?DateTimeInterface $before = null, ?WebarchiveDTO $knownArchive = null): string
    {
        // HACK : Temporary skip transform on archiver URL (éviter archive IA sur url Wikiwix)
        if ($this->isWebArchiveUrl($url)) {
            $this->log->notice('Skip {lien brisé} on web archive url', ['stats' => 'externref.skip.lienBriseOnwebarchiveurl']);
            return $url;
        }

        $fromKnownArchive = $this->tryKnownArchive($knownArchive, $summary);
        if ($fromKnownArchive !== null) {
            return $fromKnownArchive;
        }

        foreach ($this->archivers as $archiver) {
            if (!$archiver instanceof DeadlinkArchiverInterface) {
                continue;
            }
            $candidates = $archiver->searchWebarchiveCandidates($url, $now, self::MAX_CANDIDATES_PER_ARCHIVER, $before);
            if (empty($candidates)) {
                // fallback for archivers only implementing the single-result method
                $single = $archiver->searchWebarchive($url);
                $candidates = $single instanceof WebarchiveDTO ? [$single] : [];
            }

            foreach ($candidates as $webarchiveDTO) {
                $this->log->debug('Trying archive candidate: ' . $webarchiveDTO->getArchiveUrl());
                $lienWeb = $this->generateLienWebFromArchive($webarchiveDTO);
                if ($lienWeb !== null) {
                    $this->recordArchiverUsed($webarchiveDTO, $summary);

                    return $lienWeb;
                }
                $this->log->notice('Archive candidate unusable, trying next: ' . $webarchiveDTO->getArchiveUrl());
            }
        }
        $this->log->notice('web archive not found');

        return $this->generateLienBrise($url, $now, $summary, $httpStatus);
    }

    /**
     * Candidate #0 : the archive the citation already carried. Goes through the exact
     * same generateLienWebFromArchive() as a searched candidate, so it is fetched and
     * validated (blank page, parked domain, soft 404, archive viewer shell...) before
     * being trusted — "idéalement, vérifier que l'URL d'archive répond OK" comes for free
     * from that shared path rather than from a second implementation here.
     *
     * Preferred over a search because the editor's own archive-url is, by construction,
     * a snapshot contemporary with the citation : the incident this was built for
     * (2026-08-20, "April the Tapir dead") had the bot discard a 2013-11-04 snapshot
     * matching the article's own |date=1/11/2013 and search up a 2020-09-21 one instead.
     *
     * Two distinct counters, both read back by ExistingRefTransformer :
     *  - 'archive known reused'   : candidate #0 worked, no archiver was queried at all ;
     *  - 'archive known rejected' : it was tried and found unusable, so the citation's own
     *    archive-url is now PROVEN bad and must not be kept alongside whatever the search
     *    below turns up.
     *
     * @return string|null the finished citation, or null to carry on with a normal search
     */
    private function tryKnownArchive(?WebarchiveDTO $knownArchive, ?Summary $summary): ?string
    {
        if (!$knownArchive instanceof WebarchiveDTO) {
            return null;
        }

        $this->log->debug('Trying KNOWN archive from citation: ' . $knownArchive->getArchiveUrl());
        $lienWeb = $this->generateLienWebFromArchive($knownArchive);
        if ($lienWeb !== null) {
            $this->recordArchiverUsed($knownArchive, $summary);
            if ($summary instanceof Summary) {
                $summary->memo['archive known reused'] = 1 + ($summary->memo['archive known reused'] ?? 0);
            }
            $this->log->notice('♻️ Reused archive-url already in the citation');

            return $lienWeb;
        }

        $this->log->notice('Known archive-url unusable, falling back to archiver search: ' . $knownArchive->getArchiveUrl());
        if ($summary instanceof Summary) {
            $summary->memo['archive known rejected'] = 1 + ($summary->memo['archive known rejected'] ?? 0);
        }

        return null;
    }

    private function recordArchiverUsed(WebarchiveDTO $dto, ?Summary $summary): void
    {
        // Literal names, not the adapters' own ARCHIVER_NAME consts : this stays
        // Infrastructure-agnostic (Domain shouldn't import concrete adapter classes
        // just to compare a string already defined on the DTO).
        if ($dto->getArchiver() === '[[Wikiwix]]') {
            $this->log->notice('🥝 Wikiwix found');
            if ($summary instanceof Summary) {
                $summary->memo['wikiwix'] = 1 + ($summary->memo['wikiwix'] ?? 0);
            }
        }
        if ($dto->getArchiver() === '[[Internet Archive]]') {
            $this->log->notice('🏛️ InternetArchive found');
            if ($summary instanceof Summary) {
                $summary->memo['wayback'] = 1 + ($summary->memo['wayback'] ?? 0);
            }
        }
    }

    /**
     * @return string|null null when the snapshot turned out to be unusable (blank page,
     *     parked domain, soft 404...) — caller should try the next candidate rather than
     *     ever surface this bare archive URL in the article (see incident 2026-08-06,
     *     docs/audit-gestion-erreurs-crawl-2026-08.md §7).
     */
    private function generateLienWebFromArchive(WebarchiveDTO $dto): ?string
    {
        sleep(self::DELAY_PARSE_ARCHIVE);

        $result = $this->externRefProcessOnArchive($dto);

        // ExternRefTransformer::process() only returns a template (starts with "{{")
        // on success ; anything else (soft failure, empty metadata...) means this
        // snapshot didn't pass SoftFailureDetector / the mapping gates.
        return str_starts_with(trim($result), '{{') ? $result : null;
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

    /**
     * Public : also used by LienBriseArchiveFixer to recognize its own placeholder
     * (vs. a human-curated titre worth preserving) on an existing {{Lien brisé}}.
     */
    public function generateTitleFromURLText(string $url): string
    {
        $text = str_replace(['https://', 'http://', 'www.'], '', $url);
        if (strlen($text) > 30) {
            $text = substr($text, 0, 30) . '…';
        }

        return $text;
    }

    protected function generateLienBrise(string $url, DateTimeInterface $now, ?Summary $summary = null, ?int $httpStatus = null): string
    {
        if ($this->isWebArchiveUrl($url)) {
            $this->log->notice('Skip {lien brisé} on web archive url', ['stats' => 'externref.skip.lienBriseOnwebarchiveurl']);

            return $url;
        }

        if ($summary instanceof Summary) {
            $summary->memo['count lien brisé'] = 1 + ($summary->memo['count lien brisé'] ?? 0);
        }

        return sprintf(
            '{{Lien brisé |url= %s |titre=%s |brisé le=%s%s}}',
            $this->stripWebArchivePrefix($url),
            $this->generateTitleFromURLText($url),
            FrenchDate::toFrenchText((int) $now->format('j'), (int) $now->format('n'), (int) $now->format('Y')),
            $httpStatus !== null ? sprintf(' |note=HTTP %d', $httpStatus) : ''
        );
    }

    /**
     * archive.today et ses domaines miroirs (archive.is/.ph/.md/.vn/.li/.fo/.ec) : mis en liste noire
     * par fr.wikipedia le 23/02/2026 (DDoS via captcha + altération de contenu constatée), cf.
     * https://fr.wikipedia.org/wiki/Wikip%C3%A9dia:Le_Bistro/21_f%C3%A9vrier_2026#archive.today
     * On garde la reconnaissance de ces domaines (pour ne pas re-traiter des liens déjà archivés
     * existants) mais on ne les utilise plus comme archiveur (cf. DeadlinkArchiverInterface).
     */
    public const ARCHIVE_TODAY_DOMAINS_REGEX = 'archive\.(today|is|ph|md|vn|li|fo|ec)';

    /**
     * Bug https://w.wiki/7kUm
     */
    private function stripWebArchivePrefix(string $url): string
    {
        $url = preg_replace('#^https?://web\.archive\.org/web/\d+/#', '', $url);
        $url = preg_replace('#^https?://' . self::ARCHIVE_TODAY_DOMAINS_REGEX . '/\d+/#', '', $url);

        return preg_replace('#^https?://archive\.wikiwix\.com/cache/\d+/#', '', $url);
    }

    /**
     * todo move
     * Public : also used by ExistingRefTransformer to skip URLs that are already an
     * archive link (nothing to refresh/dead-link-check there).
     */
    public function isWebArchiveUrl(string $url): bool
    {
        return str_starts_with($url, 'http://web.archive.org/web/')
            || str_starts_with($url, 'https://web.archive.org/web/')
            || (bool)preg_match('#^https?://' . self::ARCHIVE_TODAY_DOMAINS_REGEX . '/#', $url)
            // every wikiwix.com flavour, not just the HTTPS longform WikiwixAdapter returns :
            // "http://wikiwix.com/cache/?url=..." links sitting in articles are archive URLs too
            || WikiwixUrl::isWikiwixUrl($url);
    }
}