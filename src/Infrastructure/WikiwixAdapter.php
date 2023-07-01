<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\InfrastructurePorts\DeadlinkArchiverInterface;
use App\Domain\InfrastructurePorts\ExternHttpClientInterface;
use App\Domain\Models\WebarchiveDTO;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Get archive url from Wikiwix webarchiver API.
 *
 *  JSON response example:
 * "status": 200,
 * "contenttype": "text\/html; charset=utf-8",
 * "timestamp": 912380400,
 * "datetime": "19981130000000",
 * "longformurl": "https:\/\/archive.wikiwix.com\/cache\/19981130000000\/http:\/\/casamaures.org\/keskispas.php?lng=fr&pg=1064"
 */
class WikiwixAdapter implements DeadlinkArchiverInterface
{
    public const ARCHIVER_NAME = '[[Wikiwix]]';
    private const API_URL = 'https://archive.wikiwix.com/cache/index2.php?apiresponse=1&url=';

    public function __construct(
        protected readonly ExternHttpClientInterface $externHttpClient,
        protected readonly LoggerInterface $log = new NullLogger()
    )
    {
    }

    public function searchWebarchive(string $url): ?WebarchiveDTO
    {
        $archiveData = $this->requestWikiwixApi($url);
        if (empty($archiveData['longformurl'])) {
            return null;
        }

        return new WebarchiveDTO(
            self::ARCHIVER_NAME,
            $url,
            (string) $archiveData['longformurl'],
            $archiveData['timestamp']
                ? DateTimeImmutable::createFromFormat('U', (string)$archiveData['timestamp'])
                : null
        ); // todo factory ?
    }

    protected function requestWikiwixApi(string $url): array
    {
        $response = $this->externHttpClient->getClient()->request(
            'GET',
            self::API_URL . urlencode($url)
        );

        if (!$response instanceof ResponseInterface || $response->getStatusCode() !== 200) {
            return [];
        }
        $jsonString = $response->getBody()->getContents();
        $data = json_decode($jsonString, true) ?? [];

        // check wikiwix archive status
        if (empty($data['status']) || (int) $data['status'] !== 200) {
            $this->log->debug('WikiwixAdapter response: ' . $jsonString);

            return [];
        }

        return $data;
    }
}