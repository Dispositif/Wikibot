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
use Scriptotek\GoogleBooks\GoogleBooks;

/**
 * See https://github.com/scriptotek/php-google-books package.
 * Class GoogleBooksAdapter.
 */
class GoogleBooksAdapter extends AbstractBookApiAdapter implements BookApiInterface
{
    protected $api;

    protected $mapper;

    // todo inject + factory
    /**
     * @var GoogleQuota
     */
    private $quotaCounter;

    public function __construct()
    {
        $api = new GoogleBooks(
            [
                'key' => getenv('GOOGLE_BOOKS_API_KEY'),
                'maxResults' => 5,
                'country' => 'US',
            ]
        );
        // 'country' => 'FR' (ISO-3166 Country Codes?)
        $this->api = $api;
        $this->mapper = new GoogleBookMapper();
        $this->quotaCounter = new GoogleQuota();
    }

    /**
     * @param string $isbn
     *
     * @return mixed
     */
    public function getDataByIsbn(string $isbn)
    {
        $this->checkGoogleQuota();
        return $this->api->volumes->byIsbn($isbn);
    }

    /**
     * @param string $googleId
     *
     * @return \Scriptotek\GoogleBooks\Volume
     */
    public function getDataByGoogleId(string $googleId)
    {
        $this->checkGoogleQuota();
        return $this->api->volumes->get($googleId);
    }

    private function checkGoogleQuota(){
        if($this->quotaCounter->getCount() > 1000 ){
            throw new \DomainException('Quota Google 1000 dépassé');
        }
        $this->quotaCounter->increment();
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
