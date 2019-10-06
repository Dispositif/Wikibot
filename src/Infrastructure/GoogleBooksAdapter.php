<?php


namespace App\Infrastructure;


use App\Domain\BookApiInterface;
use App\Domain\GoogleBookMapper;
use Scriptotek\GoogleBooks\GoogleBooks;

class GoogleBooksAdapter extends AbstractBookApiAdapter implements BookApiInterface
{

    protected $api;
    protected $mapper;

    // todo inject + factory
    public function __construct()
    {
        $api = new GoogleBooks(
            ['key' => getenv('GOOGLE_BOOKS_API_KEY'), 'maxResults' => 10]
        );
        $this->api = $api;
        $this->mapper = new GoogleBookMapper();
    }


    public function getDataByIsbn(string $isbn)
    {
        return $this->api->volumes->byIsbn($isbn);
    }
}