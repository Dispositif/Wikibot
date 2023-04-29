<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Publisher\Traits;

trait AuthorConverterTrait
{
    protected function convertJsonLDAuthor(array $data, int $index): ?string
    {
        // author=Bob
        // Why restricted to index 1 ??
        if (1 === $index && $this->extractAuthorFromString($data)) {
            return $this->extractAuthorFromString($data);
        }

        if (isset($data['author']) && is_string($data['author']) && $index === 1) {
            return html_entity_decode($data['author']);
        }

        // author ['name'=>'Bob','@type'=>'Person']
        $simpleAuthor = $this->extractFromSimpleArray($data, $index);
        if ($simpleAuthor) {
            return $simpleAuthor;
        }

        // author [ 0 => ['name'=>'Bob'], 1=> ...]
        $extractAuthorFromArray = $this->extractAuthorFromIndexedArray($data, $index);
        if ($extractAuthorFromArray) {
            return $extractAuthorFromArray;
        }

        return null;
    }

    /**
     * author=Bob
     */
    protected function extractAuthorFromString(array $data): ?string
    {
        if (isset($data['author']) && is_string($data['author'])) {
            return html_entity_decode($data['author']);
        }

        return null;
    }

    /**
     * author ['name'=>'Bob','@type'=>'Person']
     */
    protected function extractFromSimpleArray(array $data, int $index): ?string
    {
        if (0 === $index
            && isset($data['author'])
            && isset($data['author']['name'])
            && (!isset($data['author']['@type'])
                || 'Person' === $data['author']['@type'])
        ) {
            if (is_string($data['author']['name'])) {
                return html_entity_decode($data['author']['name']);
            }

            return html_entity_decode((string) $data['author']['name'][0]);
        }

        return null;
    }

    /**
     * author [ 0 => ['name'=>'Bob'], 1=> ...]
     */
    protected function extractAuthorFromIndexedArray(array $data, int $index): ?string
    {
        if (isset($data['author']) && isset($data['author'][$index])
            && (!isset($data['author'][$index]['@type'])
                || 'Person' === $data['author'][$index]['@type'])
        ) {
            if (isset($data['author'][$index]['name']) && is_string($data['author'][$index]['name'])) {
                return html_entity_decode($data['author'][$index]['name']);
            }

            // "author" => [ "@type" => "Person", "name" => [] ]
            if (isset($data['author'][$index]['name'][0])) {
                return html_entity_decode((string) $data['author'][$index]['name'][0]);
            }
        }

        return null;
    }

    protected function convertInstitutionnel($data): ?string
    {
        if (isset($data['author']) && isset($data['author'][0]) && isset($data['author'][0]['@type'])
            && 'Person' !== $data['author'][0]['@type']
        ) {
            return html_entity_decode((string) $data['author'][0]['name']);
        }

        return null;
    }

    /**
     * Used by OpenGraphMapper (so also ExternMapper).
     * If more than 2 authors, reduce to the 2 first names.
     */
    protected function shrinkMultiAuthors(?string $authors): ?string
    {
        if (empty($authors)) {
            return null;
        }
        // "Bob, Martin ; Yul, Bar ; ... ; ..."
        if (preg_match('#([^;]+;[^;]+);[^;]+;.+#', $authors, $matches)) {
            return $matches[1];
        }
        // "Bob Martin, Yul Bar, ..., ...,..."
        if (preg_match('#([^,]+,[^,]+),[^,]+,.+#', $authors, $matches)) {
            return $matches[1];
        }

        return $authors;
    }

    /**
     * If more than 2 authors, return "oui" for the bibliographic parameter "et al.".
     */
    protected function isAuthorsEtAl(?string $authors): bool
    {
        if (empty($authors)) {
            return false;
        }
        return substr_count($authors, ',') >= 2 || substr_count($authors, ';') >= 1;
    }

    protected function cleanAuthor(?string $str = null): ?string
    {
        if ($str === null) {
            return null;
        }
        // "https://www.facebook.com/search/top/?q=..."
        if (preg_match('#^https?://.+#i', $str)) {
            return null;
        }
        // "Par Bob"
        if (preg_match('#^Par (.+)$#i', $str, $matches)) {
            return $matches[1];
        }

        return $str;
    }

    /**
     * Wikification des noms/acronymes d'agences de presse.
     * Note : utiliser APRES clean() et cleanAuthor() sinon bug "|"
     */
    protected function wikifyPressAgency(?string $str): ?string
    {
        if (empty($str)) {
            return null;
        }
        // skip potential wikilinks
        if (str_contains($str, '[')) {
            return $str;
        }
        $str = preg_replace('#\b(AFP)\b#i', '[[Agence France-Presse|AFP]]', $str);
        $str = str_replace('Reuters', '[[Reuters]]', $str);
        $str = str_replace('Associated Press', '[[Associated Press]]', $str);
        $str = preg_replace('#\b(PA)\b#', '[[Press Association|PA]]', $str);
        $str = preg_replace('#\b(AP)\b#', '[[Associated Press|AP]]', $str);
        $str = str_replace('Xinhua', '[[Xinhua]]', $str);
        $str = preg_replace('#\b(ATS)\b#', '[[Agence télégraphique suisse|ATS]]', $str);

        return preg_replace('#\b(PC|CP)\b#', '[[La Presse canadienne|PC]]', $str);
    }
}