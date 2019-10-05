<?php


namespace App\Infrastructure;

use App\Domain\BookApiInterface;
use App\Domain\OpenLibraryMapper;

class OpenLibraryAdapter extends AbstractBookApiAdapter implements BookApiInterface
{

    protected $api;
    protected $mapper;

    // todo refac Guzzle
    // https://openlibrary.org/dev/docs/api/books
    public function __construct()
    {
        $this->mapper = new OpenLibraryMapper();
    }

    public function getDataByIsbn(string $isbn)
    {
        $data = [];
        $isbn = str_replace('-', '', $isbn);
        // jscmd=data or jscmd=details
        $json = file_get_contents('https://openlibrary.org/api/books?bibkeys=ISBN:'.$isbn.'&format=json&jscmd=data');
        if (!empty($json)) {
            $data = json_decode($json, true);
        }

        return $data[array_key_first($data)];
    }
}
