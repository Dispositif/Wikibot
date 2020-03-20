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
 * Class LeMondeMapper
 *
 * @package App\Domain\Publisher
 */
class LeMondeMapper extends AbstractBookMapper implements MapperInterface
{
    public function process($data): array
    {
        if ('NewsArticle' !== $data['@type']) {
            throw new \Exception('not NewsArticle');
        }

        return [
            //            'langue' => 'fr',
            'périodique' => '[[Le Monde]]',
            'titre' => trim(html_entity_decode($data['headline'])),
            'lire en ligne' => $data['mainEntityOfPage']['@id'],
            //            'auteur1' => '', // y'a pas. Pfff !
            'date' => $this->convertDate($data['datePublished']), // 2020-03-20T04:31:07+01:00
        ];
    }

    private function convertDate(string $str): string
    {
        $date = new DateTime($str);

        return $date->format('d-m-Y');
    }
}
