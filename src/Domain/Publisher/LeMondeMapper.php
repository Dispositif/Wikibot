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
class LeMondeMapper extends WebMapper
{
    public function process($data): array
    {
        if(!isset($data['JSON-LD'])) {
            return [];
        }
        $ld = $data['JSON-LD'];

        if ($ld && isset($ld['@type']) && 'NewsArticle' !== $ld['@type']) {
            throw new \Exception('not NewsArticle');
        }

        return [
            //            'langue' => 'fr',
            'périodique' => '[[Le Monde]]',
            'titre' => trim(html_entity_decode($ld['headline'])),
            'lire en ligne' => $ld['mainEntityOfPage']['@id'],
            'auteur1' =>  $data['meta']['og:article:author'] ?? null,
            // ['meta']['og:article:content_tier'] === 'free'
            'date' => $this->convertDate($ld['datePublished']), // 2020-03-20T04:31:07+01:00
        ];
    }

}
