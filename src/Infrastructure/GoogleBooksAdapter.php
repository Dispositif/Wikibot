<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Publisher\BookApiInterface;
use App\Domain\Publisher\GoogleBookMapper;
use Exception;
use Scriptotek\GoogleBooks\GoogleBooks as GoogleAPI;
use Scriptotek\GoogleBooks\Volume;

/**
 * See https://github.com/scriptotek/php-google-books package.
 * Class GoogleBooksAdapter.
 */
class GoogleBooksAdapter extends AbstractBookApiAdapter implements BookApiInterface
{
    const SCRIPT_GOOGLE_QUOTA   = 900;
    const SCRIPT_GOOGLE_COUNTRY = 'US';

    protected $api;

    protected $mapper;

    // todo inject + factory
    /**
     * @var GoogleApiQuota
     */
    private $quotaCounter;

    public function __construct()
    {
        $api = new GoogleAPI(
            [
                'key' => getenv('GOOGLE_BOOKS_API_KEY'),
                'maxResults' => 5,
                'country' => self::SCRIPT_GOOGLE_COUNTRY,
            ]
        );
        // 'country' => 'FR' (ISO-3166 Country Codes?)
        $this->api = $api;
        $this->mapper = new GoogleBookMapper();
        $this->quotaCounter = new GoogleApiQuota();
    }

    /**
     * @param string $isbn
     *
     * @return mixed
     */
    public function getDataByIsbn(string $isbn)
    {
        $this->checkGoogleQuota();
        $res = $this->api->volumes->byIsbn($isbn);
        if ($res !== null) {
            $this->quotaCounter->increment();
        }

        return $res;
    }

    private function checkGoogleQuota()
    {
        if ($this->quotaCounter->getCount() > self::SCRIPT_GOOGLE_QUOTA) {
            throw new Exception('Quota Google dépassé pour ce script : '.self::SCRIPT_GOOGLE_QUOTA);
        }
    }

    /**
     * @param string $googleId
     *
     * @return Volume
     * @throws Exception
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
     * @param string $query
     *
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
