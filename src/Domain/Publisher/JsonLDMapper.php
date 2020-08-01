<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);


namespace App\Domain\Publisher;


class JsonLDMapper implements MapperInterface
{
    use ExternConverterTrait;

    public function process($jsonLD): array
    {
        return [
            'DATA-TYPE' => 'JSON-LD',
            'DATA-ARTICLE' => $jsonLD['@type'] === 'NewsArticle',

            'périodique' => $this->clean($jsonLD['publisher']['name'] ?? null),
            'titre' => $this->clean($jsonLD['headline']), // obligatoire
            'url' => $jsonLD['url'] ?? $jsonLD['mainEntityOfPage']['@id'] ?? $jsonLD['mainEntityOfPage'] ?? null,
            'date' => $this->convertDate($jsonLD['datePublished'] ?? $jsonLD['dateCreated'] ?? null), //
            // 2020-03-19T19:13:01.000Z
            'auteur1' => $this->cleanAuthor($this->wikifyPressAgency($this->convertAuteur($jsonLD, 0))),
            'auteur2' => $this->cleanAuthor($this->convertAuteur($jsonLD, 1)),
            'auteur3' => $this->cleanAuthor($this->convertAuteur($jsonLD, 2)),
            'auteur institutionnel' => $this->cleanAuthor($this->convertInstitutionnel($jsonLD)),
            //'éditeur' => $jsonLD['publisher']['name'] ?? null,
            'consulté le' => date('d-m-Y'),
            //            'url-access' => $this->convertURLaccess($jsonLD),
        ];
    }
}
