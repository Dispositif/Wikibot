<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 (c) Philippe M. <dispositif@gmail.com>
 * For the full copyright and license information, please view the LICENSE file
 */

declare(strict_types=1);

namespace App\Domain\Publisher;

use Scriptotek\GoogleBooks\Volume;

/**
 * Google mapping.
 * Doc : https://developers.google.com/books/docs/v1/reference/volumes
 * Class GoogleBookMapper.
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
        return [
            'langue' => $volume->language,
            'auteur1' => $volume->authors[0],
            'auteur2' => $volume->authors[1] ?? null,
            'auteur3' => $volume->authors[2] ?? null,
            'titre' => $volume->title,
            'sous-titre' => $volume->subtitle,
            'année' => $this->convertDate2Year($volume->publishedDate),
            'pages totales' => (string) $volume->pageCount,
            'isbn' => $this->isbn($volume),
            'présentation en ligne' => $this->presentationEnLigne($volume),
            'lire en ligne' => $this->lireEnLigne($volume),
        ];
    }

    private function convertDate2Year($data)
    {
        if (!isset($data)) {
            return null;
        }
        if (preg_match('/[^0-9]?([12][0-9]{3})[^0-9]?/', $data, $matches) > 0) {
            return (string) $matches[1];
        }

        return null;
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
        $isbn = null;
        $ids = (array) $volume->industryIdentifiers;
        foreach ($ids as $id) {
            if (!isset($isbn) && in_array($id->type, ['ISBN_10', 'ISBN_13'])) {
                $isbn = $id->identifier;
            }
            if ('ISBN_13' === $id->type) {
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
