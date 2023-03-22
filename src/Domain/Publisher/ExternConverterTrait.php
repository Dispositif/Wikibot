<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
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
        return in_array($str, ['article', 'journalArticle']);
    }

    /**
     * mapping "accès url" : libre, inscription, limité, payant/abonnement.
     * https://fr.wikipedia.org/wiki/Wikip%C3%A9dia:Le_Bistro/25_ao%C3%BBt_2020#Lien_externe_:_paramètre_pour_accessibilité_restreinte_(abonnement,_article_payant)
     *
     * @param $data
     *
     * @return string|null
     */
    protected function convertURLaccess($data): ?string
    {
        // https://developers.facebook.com/docs/instant-articles/subscriptions/content-tiering/?locale=fr_FR
        if (isset($data['og:article:content_tier'])) {
            switch (strtolower($data['og:article:content_tier'])) {
                case 'free':
                    return 'libre';
                case 'locked':
                    return 'payant';
                case 'metered':
                    return 'limité';
            }
        }

        // NYT, Figaro
        // Todo : Si pas libre => limité ou payant ?
        if (isset($data['isAccessibleForFree'])) {
            return ($this->sameAsTrue($data['isAccessibleForFree'])) ? 'libre' : 'payant';
        }

        if (isset($data['DC.rights']) && in_array(strtolower($data['DC.rights']), ['free', 'public domain', 'domaine public'])) {
            return 'libre';
        }

        // TODO : https://terms.tdwg.org/wiki/dcterms:accessRights
        // "	Information about who access the resource or an indication of its security status."
        // Values are a mystery...
        if (isset($data['DC.accessRights']) && in_array(
            strtolower($data['DC.accessRights']),
            [
                'free',
                'public domain',
                'public',
                'domaine public',
                'available',
            ]
        )) {
            return 'libre';
        }

        return null;
    }

    protected function sameAsTrue($str = null): bool
    {
        if ($str === null) {
            return false;
        }
        if (is_bool($str)) {
            return $str;
        }
        $str = strtolower($str);
        return in_array($str, ['true', '1', 'yes', 'oui', 'ok']);
    }

    /**
     * Réduit le nombre d'auteurs si > 3.
     * En $modeEtAll=true vérification pour "et al.=oui".
     * TODO : wikifyPressAgency()
     */
    protected function authorsEtAl(?string $authors, bool $modeEtAl = false): ?string
    {
        if (empty($authors)) {
            return null;
        }
        // conserve juste les 2 premiers auteurs TODO : refactor
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

    /**
     * Note : à appliquer AVANT wikification (sinon bug sur | )
     *
     * @param string|null $str
     *
     * @return string|null
     */
    public function clean(?string $str = null): ?string
    {
        if ($str === null) {
            return null;
        }
        $str = $this->stripEmailAdress($str);

        $str = str_replace(
            [
                '|',
                "\n",
                "\t",
                "\r",
                '&#x27;',
                '&#39;',
                '&#039;',
                '&apos;',
                "\n",
                "&#10;",
                "&eacute;",
                '©',
                '{{',
                '}}',
                '[[',
                ']]',
            ],
            [
                '/',
                ' ',
                ' ',
                '',
                "’",
                "'",
                "'",
                "'",
                '',
                ' ',
                "é",
                '',
                '',
                '',
                '',
                '',
            ],
            $str
        );

        $str = html_entity_decode($str);
        $str = strip_tags($str);

        return trim($str);
    }

    /**
     * Naive check for SEO title.
     */
    public function cleanSEOTitle(?string $title, $url = null): ?string
    {
        $cleanTitle = $this->clean($title);

        // TODO {titre à vérifier} + checkSEOTitle()
        if (
            null !== $cleanTitle
            && strlen($cleanTitle) >= 30
            && isset($this->titleFromHtmlState) && $this->titleFromHtmlState
        ) {
            $cleanTitle .= "<!-- Vérifiez ce titre -->";
        }

        return $cleanTitle;
    }

    public function stripEmailAdress(?string $str = null): ?string
    {
        if ($str === null) {
            return null;
        }

        return preg_replace('# ?[^ ]+@[^ ]+\.[A-Z]+#i', '', $str);
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

    protected function convertAuteur($data, $indice): ?string
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

    protected function convertInstitutionnel($data): ?string
    {
        if (isset($data['author']) && isset($data['author'][0]) && isset($data['author'][0]['@type'])
            && 'Person' !== $data['author'][0]['@type']
        ) {
            return html_entity_decode($data['author'][0]['name']);
        }

        return null;
    }

    /**
     * todo move to generalize as utility
     *
     * @throws Exception
     */
    protected function convertDate(?string $str): ?string
    {
        if (empty($str)) {
            return null;
        }
        $str = str_replace(' 00:00:00', '', $str);
        $str = str_replace('/', '-', $str);

        // "2012"
        if (preg_match('#^[12]\d{3}$#', $str)) {
            return $str;
        }
        // "1775-1783" (Gallica)
        if (preg_match('#^[12]\d{3}-[12]\d{3}$#', $str)) {
            return $str;
        }

        try {
            $date = new DateTime($str);
        } catch (Exception $e) {
            // 23/11/2015 00:00:00
            if (isset($this) && isset($this->log) && method_exists($this->log, 'notice')) {
                $this->log->notice('EXCEPTION DATE');
            }

            return '<!-- '.$str.' -->';
        }

        return $date->format('d-m-Y');
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

        return preg_replace('#\b(PC|CP)\b#', '[[La Presse canadienne|PC]]', $str);
    }

    /**
     * Add "note=" parameter/value for human information.
     */
    private function addNote()
    {
        return null;
    }
}
