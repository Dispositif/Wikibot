<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);


namespace App\Domain\Publisher;

/**
 * Mapper for lemonde.fr articles.
 * Class LeMondeMapper
 *
 * @package App\Domain\Publisher
 */
class LeMondeMapper extends WebMapper
{
    public function process($data): array
    {
        if (!isset($data['JSON-LD'])) {
            return [];
        }
        $ld = $data['JSON-LD'];

        if ($ld && isset($ld['@type']) && 'NewsArticle' !== $ld['@type']) {
            throw new \Exception('not NewsArticle');
        }

        return [
            //            'langue' => 'fr',
            'périodique' => '[[Le Monde]]',
            'titre' => trim(html_entity_decode($ld['headline'] ?? '')),
            'lire en ligne' => $ld['mainEntityOfPage']['@id'] ?? null,
            'auteur1' => $this->authorFilter($data['meta']['og:article:author'] ?? null),
            // ['meta']['og:article:content_tier'] === 'free'
            'date' => $this->convertDate($ld['datePublished'] ?? null), // 2020-03-20T04:31:07+01:00
        ];
    }

    /**
     * Filtre auteur= "Le Monde avec l'AFP" ou "AFP" ou "Le Monde".
     * https://fr.wikipedia.org/wiki/Wikip%C3%A9dia:Le_Bistro/31_mars_2020#Auteur_AFP_d'article_de_presse
     *
     * @param string|null $str
     *
     * @return string|null
     */
    protected function authorFilter(?string $str = null): ?string
    {
        if (empty($str)) {
            return null;
        }
        if ('Le Monde' === $str) {
            return null;
        }

        $str = str_replace('Le Monde', "''Le Monde''", $str);
        $str = str_replace('AFP', '[[Agence France-Presse|AFP]]', $str);

        return $str;
    }

}
