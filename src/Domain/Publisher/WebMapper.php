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
        if(!isset($data['JSON-LD'])) {
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
            'date' => $this->convertDate($data['datePublished']), // 2020-03-19T19:13:01.000Z
            'auteur1' => $this->convertAuteur($data, 0),
            'auteur2' => $this->convertAuteur($data, 1),
            'auteur3' => $this->convertAuteur($data, 2),
            'auteur institutionnel' => $this->convertInstitutionnel($data),
        ];
    }

    protected function convertAuteur($data, $indice)
    {
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
     * @throws \Exception
     */
    protected function convertDate(string $str): string
    {
        $date = new DateTime($str);

        return $date->format('d-m-Y');
    }
}
