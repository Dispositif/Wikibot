<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Publisher;

/**
 * https://openlibrary.org/dev/docs/api/books
 * Class OpenLibraryMapper.
 */
class OpenLibraryMapper extends AbstractBookMapper implements MapperInterface
{
    /**
     * @param $data array
     *
     * @return array
     */
    public function process($data): array
    {
        $details = $data['details'];

        // authors : see also 'contributions'
        return [
            'auteur1' => $details['authors'][0]['name'] ?? null,
            'auteur2' => $details['authors'][1]['name'] ?? null,
            'auteur3' => $details['authors'][2]['name'] ?? null,
            'titre' => $details['title'] ?? null,
            'sous-titre' => $this->filterSubtitle($details),
            'éditeur' => $details['publishers'][0]['name'] ?? null,
            'année' => $this->convertDate2Year($details),
            'lieu' => ($details['publish_places'][0]['name']) ?? null,
            'pages totales' => $this->nbPages($details),
            'lire en ligne' => $this->readOnline($data),
//            'présentation en ligne' => $this->previewOnline($data), // pas de consensus
        ];
    }

    private function filterSubtitle(array $details): ?string
    {
        if (!isset($details['subtitle']) || 'roman' === mb_strtolower($details['subtitle'])) {
            return null;
        }

        if(strlen($details['subtitle']) > 80 ) {
            return null;
        }

        return $details['subtitle'];
    }

    /**
     * TODO : lire en ligne !
     * preview : Preview state - either "noview" or "full".
     * preview_url : A URL to the preview of the book.
     * This links to the archive.org page when a readable version of the book is available, otherwise it links to the
     * book page on openlibrary.org.
     * Please note that the preview_url is always provided even if there is no readable version available. The preview
     * property should be used to test if a book is readable.
     *
     * @param $data
     *
     * @return string|null
     */
    private function readOnline($data): ?string
    {
        if (!empty($data['preview_url']) && isset($data['preview']) && 'full' === $data['preview']) {
            return $data['preview_url'];
        }

        return null;
    }

    // Emprunt en ligne Internet Archive
    private function previewOnline($data): ?string
    {
        if (!empty($data['preview_url']) && isset($data['preview']) && 'borrow' === $data['preview']) {
            return $data['preview_url'];
        }

        return null;
    }

    private function convertDate2Year($details)
    {
        if (!isset($details['publish_date'])) {
            return null;
        }
        if (preg_match('/[^0-9]?([12][0-9]{3})[^0-9]?/', $details['publish_date'], $matches) > 0) {
            return (string)$matches[1];
        }

        return null;
    }

    private function nbPages($details)
    {
        return (isset($details['number_of_pages'])) ? (intval($details['number_of_pages'])) : null;
    }
}
