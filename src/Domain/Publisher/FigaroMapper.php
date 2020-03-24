<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);


namespace App\Domain\Publisher;


/**
 * Class FigaroMapper
 *
 * @package App\Domain\Publisher
 */
class FigaroMapper extends WebMapper
{
    const PERIODIQUE = '[[Le Figaro]]';

    public function process($data): array
    {
        if (!isset($data['JSON-LD'])) {
            return [];
        }
        $data = $data['JSON-LD'];

        foreach ($data as $dat) {
            if ('NewsArticle' === $dat['@type']) {
                $data = $dat;
                goto mapping;
            }
            // todo ('WebPage' == @type)  ==> {{lien web}} ?
        }

        return []; // exception ?

        mapping:

        return [
            //            'langue' => 'fr',
            'périodique' => static::PERIODIQUE,
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

}
