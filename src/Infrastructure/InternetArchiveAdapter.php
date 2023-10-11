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
use DateTime;
use DateTimeInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * https://archive.org/help/wayback_api.php
 * todo closest by date
 */
class InternetArchiveAdapter implements DeadlinkArchiverInterface
{
    final public const ARCHIVER_NAME = '[[Internet Archive]]'; // [[Wayback Machine]] ?
    private const SEARCH_CLOSEST_TIMESTAMP = '20220101';

    public function __construct(
        protected readonly HttpClientInterface $client,
        protected readonly LoggerInterface     $log = new NullLogger()
    )
    {
    }

    public function searchWebarchive(string $url, ?DateTimeInterface $date = null): ?WebarchiveDTO
    {
        $archiveData = $this->requestInternetArchiveApi($url, $date);
        if (empty($archiveData)) {
            return null;
        }

        $iaDateOrNull = $this->convertIATimestampToDateTime($archiveData['timestamp'] ?? null);

        return new WebarchiveDTO(
            self::ARCHIVER_NAME,
            $url,
            (string)$archiveData['url'],
            $iaDateOrNull
        );
    }

    protected function requestInternetArchiveApi(string $url, ?DateTimeInterface $date = null): array
    {
        $response = $this->client->get(
            'https://archive.org/wayback/available?timestamp=' . self::SEARCH_CLOSEST_TIMESTAMP . '&url=' . urlencode($url),
            [
                'timeout' => 20,
                'allow_redirects' => true,
                'headers' => ['User-Agent' => getenv('USER_AGENT')],
                'http_errors' => false, // no Exception on 4xx 5xx
                'verify' => false,
            ]
        );

        if ($response->getStatusCode() !== 200) {
            $this->log->debug('InternetArchive: incorrect response', [
                'status' => $response->getStatusCode(),
                'content-type' => $response->getHeader('Content-Type'),
            ]);
            return [];
        }
        $jsonString = $response->getBody()->getContents();
        $data = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR) ?? [];

        if (!isset($data['archived_snapshots']['closest'])) {
            $this->log->info('InternetArchive: no closest snapshot', [
                'url' => $url,
                'date' => $date,
                'json' => $jsonString,
            ]);

            return [];
        }

        // validate snapshot data
        $closest = $data['archived_snapshots']['closest'];
        if ($closest['status'] !== "200" || $closest['available'] !== true || empty($closest['url'])) {
            $this->log->debug('InternetArchive: snapshot invalid', $closest);
            return [];
        }

        return $closest;
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