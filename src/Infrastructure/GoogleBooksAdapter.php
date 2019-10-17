<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Publisher\BookApiInterface;
use App\Domain\Publisher\GoogleBookMapper;
use Scriptotek\GoogleBooks\GoogleBooks;

/**
 * See https://github.com/scriptotek/php-google-books package.
 * Class GoogleBooksAdapter
 */
class GoogleBooksAdapter extends AbstractBookApiAdapter implements BookApiInterface
{
    protected $api;

    protected $mapper;

    // todo inject + factory
    public function __construct()
    {
        $api = new GoogleBooks(
            ['key' => getenv('GOOGLE_BOOKS_API_KEY'), 'maxResults' => 5]
        );
        // 'country' => 'NO' (ISO639)
        $this->api = $api;
        $this->mapper = new GoogleBookMapper();
    }

    public function getDataByIsbn(string $isbn)
    {
        return $this->api->volumes->byIsbn($isbn);
    }

    public function getDataByGoogleId(string $googleId)
    {
        return $this->api->volumes->get($googleId);
    }

    /**
     * @param string $query
     *
     * @return \Generator|\Scriptotek\GoogleBooks\Volume
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
