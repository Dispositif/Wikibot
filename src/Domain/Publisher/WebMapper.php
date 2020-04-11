<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Publisher;

use App\Domain\Enums\Language;
use App\Domain\Utils\ArrayProcessTrait;
use DateTime;

/**
 * Generic mapper for press/revue article on web.
 * Using JSON-LD and meta tags to obtain {article} data.
 * Class WebMapper
 *
 * @package App\Domain\Publisher
 */
class WebMapper implements MapperInterface
{
    use ArrayProcessTrait;

    public function process($data): array
    {
        $dat = $this->processMapping($data);

        return $this->postProcess($dat);
    }

    protected function postProcess(array $dat): array
    {
        $dat = $this->deleteEmptyValueArray($dat);
        if (isset($dat['langue']) && 'fr' === $dat['langue']) {
            unset($dat['langue']);
        }

        return $dat;
    }

    protected function processMapping($data): array
    {
        if (!empty($data['JSON-LD'])) {
            if ($this->checkJSONLD($data['JSON-LD'])) {
                return $this->mapArticleDataFromJSONLD($data['JSON-LD']);
            }
            // gestion des multiples objets comme Figaro
            foreach ($data['JSON-LD'] as $dat) {
                if (is_array($dat) && $this->checkJSONLD($dat)) {
                    return $this->mapArticleDataFromJSONLD($dat);
                }
            }
        }
        if (!empty($data['meta'])) {
            // Dublin Core mapping included ;-)
            return $this->mapLienwebFromOpenGraph($data['meta']);
        }

        return [];
    }

    protected function checkJSONLD(array $jsonLD): bool
    {
        return isset($jsonLD['headline']) && isset($jsonLD['@type']);
    }

    protected function mapArticleDataFromJSONLD(array $jsonLD): array
    {
        return [
            'DATA-TYPE' => 'JSON-LD',
            'DATA-ARTICLE' => $jsonLD['@type'] === 'NewsArticle',
            'périodique' => $jsonLD['publisher']['name'] ?? null,
            'titre' => html_entity_decode($jsonLD['headline']), // obligatoire
            'url' => $jsonLD['url'] ?? $jsonLD['mainEntityOfPage']['@id'] ?? null,
            'date' => $this->convertDate($jsonLD['datePublished'] ?? null), // 2020-03-19T19:13:01.000Z
            'auteur1' => $this->wikifyPressAgency($this->convertAuteur($jsonLD, 0)),
            'auteur2' => $this->convertAuteur($jsonLD, 1),
            'auteur3' => $this->convertAuteur($jsonLD, 2),
            'auteur institutionnel' => $this->convertInstitutionnel($jsonLD),
            'url-access' => $this->convertURLaccess($jsonLD),
        ];
    }

    /**
     * Mapping from Open Graph and Dublin Core meta tags
     * https://ogp.me/
     * https://www.dublincore.org/schemas/
     *
     * @param array $meta
     *
     * @return array
     */
    protected function mapLienwebFromOpenGraph(array $meta): array
    {
        return [
            'DATA-TYPE' => 'Open Graph/Dublin Core',
            'DATA-ARTICLE' => $this->isAnArticle($meta['og:type'] ?? ''),
            'site' => $this->clean($meta['og:site_name'] ?? null),
            'titre' => $this->clean($meta['og:title'] ?? $meta['twitter:title'] ?? $meta['DC.title'] ?? null),
            'url' => $meta['og:url'] ?? $meta['URL'] ?? null,
            'langue' => $this->convertLangue(
                $meta['og:locale'] ?? $meta['DC.language'] ?? $meta['citation_language'] ?? null
            ),
            'consulté le' => date('d-m-Y'),
            'auteur' => $this->clean(
                $meta['og:article:author'] ?? $meta['article:author'] ?? $meta['citation_author'] ?? null
            ),
            'format' => $this->convertOGtype2format($meta['og:type'] ?? null),
            'date' => $this->convertDate(
                $meta['og:article:published_time'] ??
                $meta['article:published_time'] ?? $meta['DC.date'] ?? $meta['citation_date'] ?? null
            ),
            'url-access' => $this->convertURLaccess($meta),

            // DUBLIN CORE ONLY
            'périodique' => $this->clean($meta['DC.isPartOf'] ?? $meta['citation_journal_title'] ?? null),
            'et al.' => $this->authorsEtAl(
                $meta['citation_authors'] ?? $meta['DC.Contributor'] ?? null,
                true
            ),
            'auteur1' => $this->wikifyPressAgency(
                $this->clean(
                    $this->authorsEtAl(
                        $meta['citation_authors'] ?? $meta['DC.Contributor'] ?? null
                    )
                )
            ),
            'volume' => $meta['citation_volume'] ?? null,
            'numéro' => $meta['citation_issue'] ?? null,
            'page' => $this->convertDCpage($meta),
            'doi' => $meta['citation_doi'] ?? null,
            'éditeur' => $meta['DC.publisher'] ?? null, // Persée dégeulasse todo?
            'pmid' => $meta['citation_pmid'] ?? null,
            'issn' => $meta["citation_issn"] ?? null,
            'isbn' => $meta["citation_isbn"] ?? null,
            // "prism.eIssn" => "2262-7197"
        ];
    }

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
            return ($data['DC.rights'] === 'free') ? 'ouvert' : 'limité';
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

    protected function clean(?string $str = null): ?string
    {
        if ($str === null) {
            return null;
        }
        $str = str_replace(['&apos;', "\n"], ["'", ' '], $str);

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
            if (is_string($data['author'][$indice]['name'])) {
                return html_entity_decode($data['author'][$indice]['name']);
            }

            // "author" => [ "@type" => "Person", "name" => [] ]
            return html_entity_decode($data['author'][$indice]['name'][0]);
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
        } catch (\Exception $e) {
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
    protected function wikifyPressAgency(string $str): string
    {
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
