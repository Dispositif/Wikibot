<?php

namespace App\Domain;

/**
 * https://openlibrary.org/dev/docs/api/books
 * Class OpenLibraryMapper
 *
 * @package App\Domain
 */
class OpenLibraryMapper implements MapperInterface
{
    /**
     * @param $data array
     *
     * @return array
     */
    public function process($data): array
    {
        return [
            'auteur1' => $data['authors'][0]['name'] ?? null,
            'auteur2' => $data['authors'][1]['name'] ?? null,
            'auteur3' => $data['authors'][2]['name'] ?? null,
            'titre' => $data['title'] ?? null,
            'sous-titre' => '',
            'éditeur' => $data['publishers'][0]['name'] ?? null,
            'date' => $data['publish_date'] ?? null,
            'lieu' => ($data['publish_places'][0]['name']) ?? null,
            'pages totales' => (string)$data['number_of_pages'] ?? null,
        ];
    }

}
