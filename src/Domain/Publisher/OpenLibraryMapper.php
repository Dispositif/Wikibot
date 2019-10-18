<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 (c) Philippe M. <dispositif@gmail.com>
 * For the full copyright and license information, please view the LICENSE file
 */

declare(strict_types=1);

namespace App\Domain\Publisher;

/**
 * https://openlibrary.org/dev/docs/api/books
 * Class OpenLibraryMapper.
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
            'année' => $this->convertDate2Year($data),
            'lieu' => ($data['publish_places'][0]['name']) ?? null,
            'pages totales' => $this->nbPages($data),
        ];
    }

    private function convertDate2Year($data)
    {
        if (!isset($data['publish_date'])) {
            return null;
        }
        if (preg_match('/[^0-9]?([12][0-9]{3})[^0-9]?/', $data['publish_date'], $matches) > 0) {
            return (string) $matches[1];
        }

        return null;
    }

    private function nbPages($data)
    {
        return (isset($data['number_of_pages'])) ? (intval($data['number_of_pages'])) : null;
    }
}
