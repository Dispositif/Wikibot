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
class LiberationMapper extends AbstractBookMapper implements MapperInterface
{
    public function process($data): array
    {
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

    private function convertAuteur($data, $indice)
    {
        if (isset($data['author']) && isset($data['author'][$indice])
            && (!isset($data['author'][$indice]['@type'])
                || 'Person' === $data['author'][$indice]['@type'])
        ) {
            return html_entity_decode($data['author'][$indice]['name']);
        }

        return null;
    }

    private function convertInstitutionnel($data)
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
    private function convertDate(string $str): string
    {
        $date = new DateTime($str);

        return $date->format('d-m-Y');
    }
}
