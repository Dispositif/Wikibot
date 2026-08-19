<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Application\InfrastructurePorts\HttpClientInterface;
use App\Domain\InfrastructurePorts\DeadlinkArchiverInterface;
use App\Domain\Models\WebarchiveDTO;
use App\Infrastructure\Monitor\NullLogger;
use DateTime;
use DateTimeInterface;
use JsonException;
use Psr\Log\LoggerInterface;

/**
 * https://archive.org/help/wayback_api.php (availability API, kept as a fallback)
 * https://web.archive.org/cdx/search/cdx (CDX API, used for candidate listing : gives
 * the *real* HTTP status of each snapshot and lets us pick one close to the actual
 * consultation date, instead of trusting a single "closest" result to a fixed
 * reference timestamp).
 */
class InternetArchiveAdapter implements DeadlinkArchiverInterface
{
    final public const ARCHIVER_NAME = '[[Internet Archive]]'; // [[Wayback Machine]] ?
    private const CDX_URL = 'https://web.archive.org/cdx/search/cdx';

    public function __construct(
        protected readonly HttpClientInterface $client,
        protected readonly LoggerInterface     $log = new NullLogger()
    )
    {
    }

    public function searchWebarchive(string $url, ?DateTimeInterface $date = null): ?WebarchiveDTO
    {
        $candidates = $this->searchWebarchiveCandidates($url, $date, 1);

        return $candidates[0] ?? null;
    }

    /**
     * @return WebarchiveDTO[] ordered closest-to-$date first (or most recent if $date is null)
     */
    public function searchWebarchiveCandidates(string $url, ?DateTimeInterface $date = null, int $limit = 5, ?DateTimeInterface $before = null): array
    {
        $rows = $this->requestCdxApi($url, $date, $limit, $before);

        return array_map(
            fn(array $row) => new WebarchiveDTO(
                self::ARCHIVER_NAME,
                $url,
                'https://web.archive.org/web/' . $row['timestamp'] . '/' . $row['original'],
                $this->convertIATimestampToDateTime($row['timestamp'])
            ),
            $rows
        );
    }

    /**
     * @return array<int, array{timestamp: string, original: string}>
     */
    protected function requestCdxApi(string $url, ?DateTimeInterface $date, int $limit, ?DateTimeInterface $before = null): array
    {
        // CDX expects repeated "filter=" params (not the filter[]=... bracket form
        // http_build_query would produce for an array value), so build it by hand.
        $params = [
            'url=' . urlencode($url),
            'output=json',
            'filter=' . urlencode('statuscode:200'),
            'filter=' . urlencode('!mimetype:warc/revisit'),
            'collapse=digest', // avoid piling up many identical snapshots (e.g. a parked domain re-crawled for years)
            'limit=' . (max(1, $limit) * 3), // over-fetch a bit : some rows get discarded below
        ];
        if ($before instanceof DateTimeInterface) {
            // CDX's own "to=" filter, not just a lower rank : a caller passing this
            // already knows the closest-to-$date neighborhood came back unusable
            // (soft-404/parking, typically a cybersquat sometime after $date) and wants
            // candidates genuinely excluded, not merely deprioritized -- see
            // DeadlinkArchiverInterface::searchWebarchiveCandidates()'s docblock.
            $params[] = 'to=' . $before->format('Ymd');
        }
        $queryString = implode('&', $params);

        $response = $this->client->get(
            self::CDX_URL . '?' . $queryString,
            [
                'timeout' => 20,
                'allow_redirects' => true,
                'headers' => ['User-Agent' => getenv('USER_AGENT')],
                'http_errors' => false,
                'verify' => false,
            ]
        );

        if ($response->getStatusCode() !== 200) {
            $this->log->debug('InternetArchive CDX: incorrect response', ['status' => $response->getStatusCode()]);

            return [];
        }

        $jsonString = $response->getBody()->getContents();
        if (trim($jsonString) === '') {
            return []; // CDX returns an empty body (not "[]") when there's no match at all
        }

        try {
            $data = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR) ?? [];
        } catch (JsonException $e) {
            $this->log->debug('InternetArchive CDX: non-JSON response: ' . $e->getMessage(), ['url' => $url]);

            return [];
        }

        // first row is the column header, e.g. ["urlkey","timestamp","original","mimetype","statuscode","digest","length"]
        $header = array_shift($data);
        if (!is_array($header)) {
            return [];
        }
        $timestampIndex = array_search('timestamp', $header, true);
        $originalIndex = array_search('original', $header, true);
        if ($timestampIndex === false || $originalIndex === false) {
            return [];
        }

        $rows = [];
        foreach ($data as $row) {
            if (!is_array($row) || !isset($row[$timestampIndex], $row[$originalIndex])) {
                continue;
            }
            $rows[] = ['timestamp' => (string)$row[$timestampIndex], 'original' => (string)$row[$originalIndex]];
        }

        return $this->sortByClosenessToDate($rows, $date, $limit);
    }

    /**
     * @param array<int, array{timestamp: string, original: string}> $rows
     * @return array<int, array{timestamp: string, original: string}>
     */
    private function sortByClosenessToDate(array $rows, ?DateTimeInterface $date, int $limit): array
    {
        if ($date instanceof DateTimeInterface) {
            $targetTimestamp = (int)$date->format('YmdHis');
            usort($rows, static fn(array $a, array $b) => abs((int)$a['timestamp'] - $targetTimestamp) <=> abs((int)$b['timestamp'] - $targetTimestamp));
        } else {
            // most recent first
            usort($rows, static fn(array $a, array $b) => $b['timestamp'] <=> $a['timestamp']);
        }

        return array_slice($rows, 0, $limit);
    }

    /**
     * "YYYYMMDDhhmmss"
     */
    protected function convertIATimestampToDateTime(?string $iaTimestamp): ?DateTimeInterface
    {
        if (empty($iaTimestamp)) {
            return null;
        }
        $iaDateTime = new DateTime();
        $iaDateTime->setDate(
            (int)substr($iaTimestamp, 0, 4),
            (int)substr($iaTimestamp, 4, 2),
            (int)substr($iaTimestamp, 6, 2)
        );
        $iaDateTime->setTime(
            (int)substr($iaTimestamp, 8, 2),
            (int)substr($iaTimestamp, 10, 2),
            (int)substr($iaTimestamp, 12, 2)
        );

        return $iaDateTime;
    }
}
