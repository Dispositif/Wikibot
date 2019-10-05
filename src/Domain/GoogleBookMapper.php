<?php

namespace App\Domain;

use Scriptotek\GoogleBooks\Volume;

/**
 * Google mapping.
 * Doc : https://developers.google.com/books/docs/v1/reference/volumes
 *
 * Class GoogleBookMapper
 *
 * @package App\Domain
 */
class GoogleBookMapper implements MapperInterface
{
    /**
     * @param $volume Volume
     *
     * @return array
     */
    public function process($volume): array
    {
        dump($volume);

        return [
            'langue' => $volume->language,
            'auteur1' => $volume->authors[0],
            'auteur2' => $volume->authors[1] ?? null,
            'auteur3' => $volume->authors[2] ?? null,
            'titre' => $volume->title,
            'sous-titre' => $volume->subtitle,
            'date' => $volume->publishedDate, // todo convert 'année'
            'pages totales' => (string)$volume->pageCount,
            'isbn' => $this->isbn($volume),
            'présentation en ligne' => $this->presentationEnLigne($volume),
            'lire en ligne' => $this->lireEnLigne($volume),
        ];
    }

    /**
     * @param Volume $volume
     *
     * @return string|null
     */
    private function isbn(Volume $volume): ?string
    {
        if (!isset($volume->industryIdentifiers)) {
            return null;
        }
        // so isbn-13 replace isbn-10
        // todo refac algo (if 2x isbn13?)
        $ids = (array)$volume->industryIdentifiers;
        foreach ($ids as $id) {
            if (!isset($isbn) && in_array($id->type, ['ISBN_10', 'ISBN_13'])) {
                $isbn = $id->identifier;
            }
            if ($id->type === 'ISBN_13') {
                $isbn = $id->identifier;
            }
        }

        return $isbn;
    }

    private function presentationEnLigne($volume): ?string
    {
        if (in_array($volume->accessInfo->viewability, ['PARTIAL'])) {
            return sprintf('{{Google Livres|%s}}', $volume->id);
        }

        return null;
    }

    private function lireEnLigne($volume): ?string
    {
        if (in_array($volume->accessInfo->viewability, ['ALL_PAGES'])) {
            return sprintf('{{Google Livres|%s}}', $volume->id);
        }

        return null;
    }
}
