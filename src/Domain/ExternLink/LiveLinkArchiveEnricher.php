<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2026 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\ExternLink;

use App\Domain\ExternLink\Validators\ArchiveNoContentValidator;
use App\Domain\InfrastructurePorts\DeadlinkArchiverInterface;
use App\Domain\Models\WebarchiveDTO;
use App\Infrastructure\Monitor\NullLogger;
use DateTimeInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Given a URL that's still ALIVE, look for an existing web-archive snapshot worth
 * attaching to the citation as a fallback (archive-url/-date), without touching |url=
 * itself -- the counterpart of DeadLinkTransformer for a link that hasn't actually died.
 * Opt-in (see ExistingRefTransformer's constructor) : this adds one or more HTTP fetches
 * per archive candidate tried, so it's never on by default.
 *
 * Takes the live page's body as a PARAMETER (see enrich()), never fetches it itself : an
 * earlier version re-fetched the live URL independently, duplicating the fetch
 * ExternRefTransformer::process() already made to build the citation in the first place
 * -- found unacceptable 2026-08-20 (~5s wasted per ref, on top of already-tight timing).
 * ExternRefTransformerInterface::getLastFetchResult() exists specifically so
 * ExistingRefTransformer can hand that SAME fetch down here instead.
 *
 * Wayback candidates come from real search ($archivers, real CDX listing, ranked by
 * date) ; Wikiwix has no listing API left (see WikiwixAdapter's own docblock) so its one
 * candidate is built directly here (WikiwixUrl::buildViewerUrl(), a pure URL format, no
 * network call) and tried last, after every Wayback candidate. Deliberately NOT going
 * through WikiwixAdapter::searchWebarchive() : that method already runs its own
 * resolve+fetch+validate cycle just to answer "does a snapshot exist", and this class
 * immediately repeats resolve+fetch+validate on the SAME url right after for content
 * comparison -- doubling Wikiwix's ~3s handshake delay and full-page fetch for nothing
 * (found empirically 2026-08-20 : 72s for a single ref). evaluateCandidate()'s own
 * ArchiveNoContentValidator check already answers "does a snapshot exist" as a side
 * effect of the one resolve+fetch it needs anyway.
 *
 * Different question from DeadLinkTransformer, which asks "is there ANY usable
 * snapshot" : this asks "is there a snapshot whose CONTENT actually matches the live
 * page" -- see ContentSimilarityScorer. Binary decision, no middle tier (2026-08-20,
 * see LiveArchiveMatch's docblock) : a candidate scoring below SCORE_THRESHOLD isn't
 * "flagged for review", it's simply not returned -- evaluateCandidate() moves on to the
 * next candidate (a low score is often just the wrong snapshot, a redesign captured at
 * an unrelated date, not proof the two differ), and if NOTHING clears the threshold
 * across every candidate tried, enrich() returns null and the citation is left
 * completely untouched.
 *
 * Never persists page content anywhere : bodies are held only for the duration of one
 * enrich() call and unset() as soon as their fingerprint/title has been extracted, so a
 * run over many refs never accumulates page text in memory.
 */
final class LiveLinkArchiveEnricher
{
    private const MAX_CANDIDATES_PER_ARCHIVER = 3;

    /** Binary : score >= this attaches the archive, anything below is left untouched. */
    private const SCORE_THRESHOLD = 85.0;

    /**
     * Doesn't prevent the download itself (the body is already fully read into memory by
     * ExternPageFactory/Guzzle by the time this is checked -- see the 2026-08-20 design
     * discussion on the shared fetcher's lack of a streaming/size-capped mode, left
     * untouched here as out of scope for this feature). Bounds how long an oversized body
     * is kept around for, though : rejected immediately rather than fed to the scorer.
     * Applies to the live body (checked at the top of enrich()) and to each archive
     * candidate body (checked in fetchSafely()).
     */
    private const MAX_BODY_BYTES = 8_000_000;

    /**
     * Wall-clock budget for the CANDIDATE-EVALUATION loop only (after the live fetch,
     * which has its own bound via ExternPageFactory's per-request timeout). Each
     * candidate can legitimately cost 10-30s (Wikiwix's handshake alone is a fixed 3s
     * sleep plus two network round trips), and per-request timeouts (20s CDX, 20s
     * Wikiwix handshake, 35s page fetch) only bound ONE request each -- nothing
     * previously bounded the SUM across candidates. Found live 2026-08-20 : a single
     * ref took 72.8s end to end, one real 20s CDX timeout included ; with up to 4
     * candidates (3 Wayback + 1 Wikiwix) all timing out, the unbounded worst case was
     * well over 2 minutes for one ref. Checked BEFORE starting each new candidate, not
     * a hard kill mid-request (an in-flight fetch still runs to its own timeout) : this
     * bounds how many MORE candidates get tried once time is already tight, not the
     * last one already in progress.
     */
    private const MAX_CANDIDATE_LOOP_SECONDS = 45.0;

    /**
     * @param DeadlinkArchiverInterface[] $archivers Real-search archivers only (Wayback) --
     *     Wikiwix is handled separately, see class docblock. Passing a WikiwixAdapter here
     *     would work but reintroduces the double-fetch this class exists to avoid.
     * @param ExternPageFactory $archiveFetcher fetches archive candidate bodies -- never over
     *     Tor, same reasoning as DeadLinkTransformer (nothing to anonymize towards an archive
     *     service, and Wikiwix's handshake token is tied to the requesting IP)
     */
    public function __construct(
        private readonly array $archivers,
        private readonly ExternPageFactory $archiveFetcher,
        private readonly WikiwixContentResolver $wikiwixContentResolver,
        private readonly ContentSimilarityScorer $scorer = new ContentSimilarityScorer(),
        private readonly LoggerInterface $log = new NullLogger(),
    ) {
    }

    /**
     * @param string $liveBody The live page's body, from the SAME fetch already made to
     *     build the citation (see class docblock) -- not fetched again here.
     */
    public function enrich(string $url, string $liveBody, ?DateTimeInterface $targetDate = null): ?LiveArchiveMatch
    {
        if ($liveBody === '' || strlen($liveBody) > self::MAX_BODY_BYTES) {
            return null;
        }

        $candidates = [];
        foreach ($this->archivers as $archiver) {
            if (!$archiver instanceof DeadlinkArchiverInterface) {
                continue;
            }
            array_push($candidates, ...$archiver->searchWebarchiveCandidates($url, $targetDate, self::MAX_CANDIDATES_PER_ARCHIVER));
        }
        $candidates[] = new WebarchiveDTO(WikiwixUrl::ARCHIVER_WIKILINK, $url, WikiwixUrl::buildViewerUrl($url), null);

        $deadline = microtime(true) + self::MAX_CANDIDATE_LOOP_SECONDS;
        foreach ($candidates as $i => $dto) {
            if (microtime(true) > $deadline) {
                $this->log->notice(
                    sprintf(
                        'LiveLinkArchiveEnricher: %.0fs budget exhausted, skipping %d remaining candidate(s)',
                        self::MAX_CANDIDATE_LOOP_SECONDS,
                        count($candidates) - $i
                    ),
                    ['stats' => 'livearchive.skip.budgetExhausted', 'url' => $url]
                );
                break;
            }
            $match = $this->evaluateCandidate($dto, $liveBody);
            if ($match !== null) {
                return $match; // good enough, stop looking
            }
        }

        $this->log->debug('LiveLinkArchiveEnricher: no candidate cleared the threshold', ['url' => $url]);

        return null;
    }

    private function evaluateCandidate(WebarchiveDTO $dto, string $liveBody): ?LiveArchiveMatch
    {
        $contentUrl = WikiwixUrl::isWikiwixUrl($dto->getArchiveUrl())
            ? ($this->wikiwixContentResolver->resolveContentUrl($dto->getArchiveUrl()) ?? $dto->getArchiveUrl())
            : $dto->getArchiveUrl();

        $archiveFetch = $this->fetchSafely($this->archiveFetcher, $contentUrl);
        if ($archiveFetch === null) {
            $this->log->debug('LiveLinkArchiveEnricher: candidate fetch failed', ['url' => $contentUrl]);

            return null;
        }
        $archiveBody = (string) $archiveFetch->body;
        unset($archiveFetch);

        // Same soft-404/viewer-shell markers the crawl pipeline itself gates on (single
        // source of truth, see WikiwixAdapter) : a candidate that fails this isn't
        // "content differs", it's "no real snapshot was served at all" -- try the next one.
        $noContent = new ArchiveNoContentValidator(['meta' => []], $dto->getArchiveUrl(), $archiveBody, $this->log);
        if ($noContent->check() !== LinkVerdict::Accept) {
            $this->log->debug('LiveLinkArchiveEnricher: no real snapshot content', ['url' => $dto->getArchiveUrl()]);
            unset($archiveBody);

            return null;
        }

        $score = $this->scorer->score($liveBody, $archiveBody);
        unset($archiveBody);

        $passed = $score >= self::SCORE_THRESHOLD;
        // notice(), not debug() : shown in the console on every run (ConsoleLogger prints
        // notice unconditionally, debug only with --debug) -- deliberately visible even
        // when the candidate DOESN'T get attached, so a human watching the run can see
        // what was found and judge the threshold, not just the citations that changed.
        $this->log->notice(
            sprintf(
                'Archive candidate (%s) : %s -- %.0f%% match, %s',
                $dto->getArchiver(),
                $dto->getArchiveUrl(),
                $score,
                $passed ? 'attaching' : 'not attached (below threshold)'
            ),
            ['stats' => 'livearchive.score.' . ($passed ? 'match' : 'belowThreshold')]
        );
        if (!$passed) {
            return null;
        }

        return new LiveArchiveMatch($dto->getArchiveUrl(), $dto->getArchiveDate(), $dto->getArchiver(), $score);
    }

    private function fetchSafely(ExternPageFactory $fetcher, string $url): ?FetchResult
    {
        try {
            $fetch = $fetcher->fetch($url);
        } catch (Throwable $e) {
            $this->log->debug('LiveLinkArchiveEnricher: fetch failed: ' . $e->getMessage(), ['url' => $url]);

            return null;
        }

        if (!$fetch->isSuccess() || $fetch->body === null) {
            return null;
        }

        if (strlen($fetch->body) > self::MAX_BODY_BYTES) {
            $this->log->notice(
                'LiveLinkArchiveEnricher: body too large, skipping',
                ['stats' => 'livearchive.skip.bodyTooLarge', 'url' => $url]
            );

            return null;
        }

        return $fetch;
    }
}
