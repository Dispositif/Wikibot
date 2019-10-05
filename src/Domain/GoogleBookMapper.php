<?php

namespace App\Domain;

use Scriptotek\GoogleBooks\Volume;

/**
 * Google mapping
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
        return ['titre' => $volume->title, 'sous-titre' => $volume->subtitle, 'auteur1' => $volume->authors[0],
                'auteur2' => $volume->authors[1] ?? null, 'auteur3' => $volume->authors[2] ?? null,
                'date' => $volume->publishedData, 'langue' => $volume->language,
                'pages totales' => (string)$volume->pageCount, 'lire en ligne' => $this->lireEnLigne($volume)];
    }

    private function lireEnLigne($volume): ?string
    {
        if (in_array($volume->accessInfo->viewability, ['NO_PAGES', 'PARTIAL'])) {
            return sprintf('{{Google Livres|%s}}', $volume->id);
        }

        return null;
    }
}
