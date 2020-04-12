<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Publisher;

use App\Domain\Enums\Language;
use Scriptotek\GoogleBooks\Volume;

/**
 * Google Books API data mapping.
 * Doc : https://developers.google.com/books/docs/v1/reference/volumes
 * Class GoogleBookMapper.
 */
class GoogleBookMapper extends AbstractBookMapper implements MapperInterface
{
    // raw URL or wiki-template ?
    const MODE_RAW_URL = true;

    const GOOGLE_URL_REPLACE = 'https://books.google.com/books?id=%s&printsec=frontcover';

    // sous-titre non ajoutés :
    const SUBTITLE_FILTER = ['roman', 'récit', 'poèmes', 'biographie'];
    /**
     * @var bool|null
     */
    private $mapLanguageData = false;

    /**
     * @param           $volume Volume
     *
     * @return array
     */
    public function process($volume): array
    {
        return [
            // language : data not accurate !
            'langue' => $this->langFilterByIsbn($volume),
            'auteur1' => $volume->authors[0],
            'auteur2' => $volume->authors[1] ?? null,
            'auteur3' => $volume->authors[2] ?? null,
            'titre' => $volume->title,
            'sous-titre' => $this->filterSubtitle($volume),
            'année' => $this->convertDate2Year($volume->publishedDate),
            'pages totales' => (string)$volume->pageCount ?? null,
            'isbn' => $this->convertIsbn($volume),
            'présentation en ligne' => $this->presentationEnLigne($volume),
            'lire en ligne' => $this->lireEnLigne($volume),
        ];
    }

    /**
     * Use the inconstant 'language' ?
     *
     * @param bool $useLanguage
     */
    public function mapLanguageData(bool $useLanguage)
    {
        $this->mapLanguageData = $useLanguage;
    }

    /**
     * @param $volume
     *
     * @return string|null
     */
    private function filterSubtitle($volume): ?string
    {
        // "biographie" ?
        if (empty($volume->subtitle) || in_array(mb_strtolower($volume->subtitle), self::SUBTITLE_FILTER)) {
            return null;
        }

        if (strlen($volume->subtitle) > 50) {
            return null;
        }

        return $volume->subtitle;
    }

    /**
     * @param $data
     *
     * @return string|null
     */
    private function convertDate2Year($data)
    {
        if (!isset($data)) {
            return null;
        }
        if (preg_match('/[^0-9]?([12][0-9]{3})[^0-9]?/', $data, $matches) > 0) {
            return (string)$matches[1];
        }

        return null;
    }

    /**
     * @param Volume $volume
     *
     * @return string|null
     */
    private function convertIsbn(Volume $volume): ?string
    {
        if (!isset($volume->industryIdentifiers)) {
            return null;
        }
        // so isbn-13 replace isbn-10
        // todo refac algo (if 2x isbn13?)
        $isbn = null;
        $ids = (array)$volume->industryIdentifiers;
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

    /**
     * @param Volume $volume
     *
     * @return string|null
     */
    private function presentationEnLigne(Volume $volume): ?string
    {
        if (empty($volume->id) || !in_array($volume->accessInfo->viewability, ['PARTIAL'])) {
            return null;
        }

        return $this->returnGoogleRef($volume);
    }

    /**
     * @param Volume $volume
     *
     * @return string|null
     */
    private function lireEnLigne(Volume $volume): ?string
    {
        if (empty($volume->id) || !in_array($volume->accessInfo->viewability, ['ALL_PAGES'])) {
            return null;
        }

        return $this->returnGoogleRef($volume);
    }

    /**
     * @param Volume $volume
     *
     * @return string
     */
    private function returnGoogleRef(Volume $volume): string
    {
        if (self::MODE_RAW_URL) {
            return sprintf(self::GOOGLE_URL_REPLACE, $volume->id);
        }

        return sprintf('{{Google Livres|%s}}', $volume->id);
    }

    /**
     * /!\ Google Books language data not consistant !
     * set $mapLanguageData to true to use that data in mapping.
     *
     * @param Volume $volume
     *
     * @return string|null
     */
    private function langFilterByIsbn(Volume $volume): ?string
    {
        if ($this->mapLanguageData !== true) {
            return null;
        }
//        $isbn = $this->convertIsbn($volume);
//        if ($isbn) {
//            try {
//                $isbnMachine = new IsbnFacade($isbn);
//                $isbnMachine->validate();
//                $isbnLang = $isbnMachine->getCountryShortName();
//            } catch (\Throwable $e) {
//                unset($e);
//            }
//        }
//        if (isset($isbnLang)) {
//            echo "(ISBN lang: ".$isbnLang.")\n";
//        } else {
//            echo "(no ISBN lang)\n";
//        }

        $gooLang = $volume->language;
        // filtering lang because seems inconsistant
//        if (isset($isbnLang) && !empty($gooLang) && $gooLang !== $isbnLang) {
//            echo sprintf(
//                "*** Filtering Google langue=%s because ISBN langue=%s ",
//                $gooLang,
//                $isbnLang
//            );
//
//            return null;
//        }

        return Language::all2wiki($gooLang);
    }
}
