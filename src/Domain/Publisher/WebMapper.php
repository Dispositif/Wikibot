<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Publisher;

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
    public function process($data): array
    {
        if (!isset($data['JSON-LD'])) {
            return [];
        }
        $data = $data['JSON-LD'];
        //
        //        foreach ($data as $dat) {
        //            if ('NewsArticle' === $dat['@type']) {
        //                $data = $dat;
        //                goto mapping;
        //            }
        //            // todo ('WebPage' == @type)  ==> {{lien web}} ?
        //        }
        //
        //        return []; // exception ?
        //
        //        mapping:

        return [
            //            'langue' => 'fr',
            'périodique' => $data['publisher']['name'] ?? null,
            //           'acces' =>  $data['isAccessibleForFree'],
            'titre' => html_entity_decode($data['headline']),
            'lire en ligne' => $data['mainEntityOfPage']['@id'],
            'date' => $this->convertDate($data['datePublished'] ?? null), // 2020-03-19T19:13:01.000Z
            'auteur1' => $this->convertAuteur($data, 0),
            'auteur2' => $this->convertAuteur($data, 1),
            'auteur3' => $this->convertAuteur($data, 2),
            'auteur institutionnel' => $this->convertInstitutionnel($data),
        ];
    }

    protected function convertAuteur($data, $indice)
    {
        // author ['name'=>'Bob','@type'=>'Person']
        if (0 === $indice
            && isset($data['author'])
            && isset($data['author']['name'])
            && (!isset($data['author']['@type'])
                || 'Person' === $data['author']['@type'])
        ) {
            return html_entity_decode($data['author']['name']);
        }

        // author [ 0 => ['name'=>'Bob'], 1=> ...]
        if (isset($data['author']) && isset($data['author'][$indice])
            && (!isset($data['author'][$indice]['@type'])
                || 'Person' === $data['author'][$indice]['@type'])
        ) {
            return html_entity_decode($data['author'][$indice]['name']);
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
        try {
            $date = new DateTime($str);
        } catch (\Exception $e) {
            return null;
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
        $str = preg_replace('#\b(AFP)\b#', '[[Agence France-Presse|AFP]]', $str);
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
