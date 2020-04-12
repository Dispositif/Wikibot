<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);


namespace App\Domain\Publisher;


trait WebLDMapperTrait
{
    protected function mapArticleDataFromJSONLD(array $jsonLD): array
    {
        return [
            'DATA-TYPE' => 'JSON-LD',
            'DATA-ARTICLE' => $jsonLD['@type'] === 'NewsArticle',
            'périodique' => $jsonLD['publisher']['name'] ?? null,
            'titre' => $this->clean($jsonLD['headline']), // obligatoire
            'url' => $jsonLD['url'] ?? $jsonLD['mainEntityOfPage']['@id'] ?? null,
            'date' => $this->convertDate($jsonLD['datePublished'] ?? $jsonLD['dateCreated'] ?? null), //
            // 2020-03-19T19:13:01.000Z
            'auteur1' => $this->wikifyPressAgency($this->convertAuteur($jsonLD, 0)),
            'auteur2' => $this->convertAuteur($jsonLD, 1),
            'auteur3' => $this->convertAuteur($jsonLD, 2),
            'auteur institutionnel' => $this->convertInstitutionnel($jsonLD),
            'url-access' => $this->convertURLaccess($jsonLD),
        ];
    }
}
