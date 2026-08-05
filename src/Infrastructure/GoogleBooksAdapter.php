<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Exceptions\QuotaExceededException;
use App\Domain\InfrastructurePorts\BookApiInterface;
use App\Domain\InfrastructurePorts\GoogleApiQuotaInterface;
use App\Domain\InfrastructurePorts\GoogleBooksInterface;
use App\Domain\Publisher\GoogleBookMapper;
use Scriptotek\GoogleBooks\GoogleBooks as GoogleAPI;
use Scriptotek\GoogleBooks\Volume;

/**
 * See https://github.com/scriptotek/php-google-books package.
 * Class GoogleBooksAdapter.
 */
class GoogleBooksAdapter extends AbstractBookApiAdapter implements GoogleBooksInterface, BookApiInterface
{
    final public const SCRIPT_GOOGLE_COUNTRY = 'US';

    protected $api;

    protected $mapper;

    private readonly GoogleApiQuotaInterface $quotaCounter;

    /**
     * @param GoogleApiQuotaInterface|null $quota Inject a shared counter to keep it in sync with other
     *                                             consumers of the same daily quota (e.g. GoogleTransformer).
     *                                             Defaults to a fresh instance (re-reads the quota file).
     * @param GoogleAPI|null $api Inject a preconfigured client (e.g. with a mock Guzzle handler) for
     *                            network-free testing. Defaults to the real Google Books API client.
     */
    public function __construct(?GoogleApiQuotaInterface $quota = null, ?GoogleAPI $api = null)
    {
        $this->api = $api ?? new GoogleAPI(
            [
                'key' => getenv('GOOGLE_BOOKS_API_KEY'),
                'maxResults' => 5,
                'country' => self::SCRIPT_GOOGLE_COUNTRY,
            ]
        );
        // 'country' => 'FR' (ISO-3166 Country Codes?)
        $this->mapper = new GoogleBookMapper();
        $this->quotaCounter = $quota ?? new GoogleApiQuota();
    }

    public function getDataByIsbn(string $isbn): ?Volume
    {
        $this->checkGoogleQuota();
        $res = $this->api->volumes->byIsbn($isbn);
        if ($res !== null) {
            $this->quotaCounter->increment();
        }

        return $res;
    }

    /**
     * @throws QuotaExceededException
     */
    private function checkGoogleQuota(): void
    {
        if ($this->quotaCounter->isQuotaReached()) {
            throw new QuotaExceededException(
                'Quota Google dépassé pour ce script : '.GoogleApiQuota::SAFE_DAILY_QUOTA
            );
        }
    }

    /**
     *
     * @return Volume
     * @throws QuotaExceededException
     */
    public function getDataByGoogleId(string $googleId)
    {
        $this->checkGoogleQuota();
        $res = $this->api->volumes->get($googleId);
        if ($res !== null) {
            $this->quotaCounter->increment();
        }

        return $res;
    }

    /**
     * @return array
     */
    public function search(string $query)
    {
        $data = [];
        foreach ($this->api->volumes->search($query) as $vol) {
            dump($vol->title);
            $data[] = $vol;
        }

        return $data;
    }
}
