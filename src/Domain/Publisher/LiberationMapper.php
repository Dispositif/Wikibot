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
 * Class LiberationMapper
 *
 * @package App\Domain\Publisher
 */
class LiberationMapper extends WebMapper
{
    public function process($data): array
    {
        if(!isset($data['JSON-LD'])) {
            return [];
        }
        $data = $data['JSON-LD'];

        return [
            //            'langue' => 'fr',
            'périodique' => '[[Libération (journal)|Libération]]',
            //           'acces' =>  $data['isAccessibleForFree'],
            'titre' => html_entity_decode($data['headline'] ?? null),
            'lire en ligne' => $data['mainEntityOfPage']['@id'],
            'date' => $this->convertDate($data['dateCreated']), //  "2017-11-17T20:06:13"
            'auteur1' => $this->convertAuteur($data, 0),
            'auteur2' => $this->convertAuteur($data, 1),
            'auteur3' => $this->convertAuteur($data, 2),
            'auteur institutionnel' => $this->convertInstitutionnel($data),
        ];
    }

}
