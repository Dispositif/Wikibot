<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);


namespace App\Domain\Publisher;

use App\Domain\Enums\Language;
use DateTime;
use Exception;

trait ExternConverterTrait
{
    protected function isAnArticle(?string $str): bool
    {
        if (in_array($str, ['article', 'journalArticle'])) {
            return true;
        }

        return false;
    }

    protected function convertURLaccess($data): ?string
    {
        // NYT, Figaro
        if (isset($data['isAccessibleForFree'])) {
            return $data['isAccessibleForFree'] ? 'ouvert' : 'limité';
        }
        if (isset($data['DC.rights'])) {
            return (in_array($data['DC.rights'], ['free', 'public domain'])) ? 'ouvert' : 'limité';
        }
        if (isset($data['og:article:content_tier'])) {
            return ($data['og:article:content_tier'] === 'free') ? 'ouvert' : 'limité';
        }

        return null;
    }

    /**
     * Réduit le nombre d'auteurs si > 3.
     * En $modeEtAll=true vérification pour "et al.=oui".
     * TODO : wikifyPressAgency()
     *
     * @param string|null $authors
     * @param bool        $modeEtAl
     *
     * @return string|null
     */
    protected function authorsEtAl(?string $authors, $modeEtAl = false): ?string
    {
        if (empty($authors)) {
            return null;
        }
        // conserve juste les 3 premiers auteurs TODO : refactor
        // Bob, Martin ; Yul, Bar ; ... ; ...
        if (preg_match('#([^;]+;[^;]+);[^;]+;.+#', $authors, $matches)) {
            return ($modeEtAl) ? 'oui' : $matches[1];
        }
        // Bob Martin, Yul Bar, ..., ...,...
        if (preg_match('#([^,]+,[^,]+),[^,]+,.+#', $authors, $matches)) {
            return ($modeEtAl) ? 'oui' : $matches[1];
        }

        return ($modeEtAl) ? null : $authors;
    }

    protected function convertDCpage(array $meta): ?string
    {
        if (isset($meta['citation_firstpage'])) {
            $page = $meta['citation_firstpage'];
            if (isset($meta['citation_lastpage'])) {
                $page .= '–'.$meta['citation_lastpage'];
            }

            return (string)$page;
        }

        return null;
    }

    public function cleanAuthor(?string $str = null): ?string
    {
        if ($str === null) {
            return null;
        }
        $str = $this->clean($str);
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

    // TODO encodage + normalizer
    public function clean(?string $str = null): ?string
    {
        if ($str === null) {
            return null;
        }
        $str = str_replace(
            ["\n", "\t", '&#x27;', '&#39;', '&#039;', '&apos;', "\n", "&#10;", "&eacute;"],
            [
                ' ',
                ' ',
                "’",
                "'",
                "'",
                "'",
                '',
                ' ',
                "é",
            ],
            $str
        );

        return html_entity_decode($str);
    }

    protected function convertOGtype2format(?string $ogType): ?string
    {
        if (empty($ogType)) {
            return null;
        }
        // og:type = default: website / video.movie / video.tv_show video.other / article, book, profile
        if (strpos($ogType, 'video') !== false) {
            return 'vidéo';
        }
        if (strpos($ogType, 'book') !== false) {
            return 'livre';
        }

        return null;
    }

    /**
     * https://developers.facebook.com/docs/internationalization#locales
     * @param string|null $lang
     *
     * @return string|null
     */
    protected function convertLangue(?string $lang = null): ?string
    {
        if (empty($lang)) {
            return null;
        }
        // en_GB
        if (preg_match('#^([a-z]{2})_[A-Z]{2}$#', $lang, $matches)) {
            return $matches[1];
        }

        return Language::all2wiki($lang);
    }

    protected function convertAuteur($data, $indice)
    {
        // author=Bob
        if (isset($data['author']) && is_string($data['author']) && $indice === 1) {
            return html_entity_decode($data['author']);
        }

        // author ['name'=>'Bob','@type'=>'Person']
        if (0 === $indice
            && isset($data['author'])
            && isset($data['author']['name'])
            && (!isset($data['author']['@type'])
                || 'Person' === $data['author']['@type'])
        ) {
            if (is_string($data['author']['name'])) {
                return html_entity_decode($data['author']['name']);
            }

            return html_entity_decode($data['author']['name'][0]);
        }

        // author [ 0 => ['name'=>'Bob'], 1=> ...]
        if (isset($data['author']) && isset($data['author'][$indice])
            && (!isset($data['author'][$indice]['@type'])
                || 'Person' === $data['author'][$indice]['@type'])
        ) {
            if (isset($data['author'][$indice]['name']) && is_string($data['author'][$indice]['name'])) {
                return html_entity_decode($data['author'][$indice]['name']);
            }

            // "author" => [ "@type" => "Person", "name" => [] ]
            if (isset($data['author'][$indice]['name'][0])) {
                return html_entity_decode($data['author'][$indice]['name'][0]);
            }
        }

        return null;
    }

    protected function convertInstitutionnel($data)
    {
        if (isset($data['author']) && isset($data['author'][0]) && isset($data['author'][0]['@type'])
            && 'Person' !== $data['author'][0]['@type']
        ) {
            return html_entity_decode($data['author'][0]['name']);
        }

        return null;
    }

    /**
     * @param string $str
     *
     * @return string
     * @throws Exception
     */
    protected function convertDate(?string $str): ?string
    {
        if (empty($str)) {
            return null;
        }

        // "2012"
        if (preg_match('#^[12][0-9]{3}$#', $str)) {
            return $str;
        }

        try {
            $date = new DateTime($str);
        } catch (Exception $e) {
            // 23/11/2015 00:00:00
            dump('EXCEPTION DATE');

            return $str;
        }

        return $date->format('d-m-Y');
    }

    /**
     * Wikification des noms/acronymes d'agences de presse.
     *
     * @param string $str
     *
     * @return string
     */
    protected function wikifyPressAgency(?string $str): ?string
    {
        if (empty($str)) {
            return null;
        }
        // skip potential wikilinks
        if (strpos($str, '[') !== false) {
            return $str;
        }
        $str = preg_replace('#\b(AFP)\b#i', '[[Agence France-Presse|AFP]]', $str);
        $str = str_replace('Reuters', '[[Reuters]]', $str);
        $str = str_replace('Associated Press', '[[Associated Press]]', $str);
        $str = preg_replace('#\b(PA)\b#', '[[Press Association|PA]]', $str);
        $str = preg_replace('#\b(AP)\b#', '[[Associated Press|AP]]', $str);
        $str = str_replace('Xinhua', '[[Xinhua]]', $str);
        $str = preg_replace('#\b(ATS)\b#', '[[Agence télégraphique suisse|ATS]]', $str);
        $str = preg_replace('#\b(PC|CP)\b#', '[[La Presse canadienne|PC]]', $str);

        return $str;
    }

}
