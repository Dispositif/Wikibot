<?php


namespace App\Infrastructure;


use App\Domain\BookApiInterface;
use Scriptotek\GoogleBooks\GoogleBooks;

class GoogleBooksAdapter extends BookApiAdapter implements BookApiInterface
{

    private $api;

    // todo inject + factory
    public function __construct()
    {
        $api = new GoogleBooks(
            ['key' => getenv('GOOGLE_BOOK_API_KEY'), 'maxResults' => 10,]
        );
        $this->api = $api;
    }

    public function getDataByIsbn(string $isbn)
    {
        return $this->api->volumes->byIsbn($isbn);
    }
}
