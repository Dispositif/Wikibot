<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Publisher\BookApiInterface;
use App\Domain\Publisher\OpenLibraryMapper;

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
        $isbn = str_replace([' ', '-'], '', $isbn);

        $url = 'https://openlibrary.org/api/books?'
             .http_build_query([
                'bibkeys' => sprintf('ISBN:%s', urlencode($isbn)),
                'format' => 'json',
                'jscmd' => 'details', // 'data' or 'details'
            ]);

        $json = file_get_contents($url);

        if (empty($json)) {
            return null;
        }

        $data = json_decode($json, true);

        return $data[array_key_first($data)] ?? null;
    }
}
