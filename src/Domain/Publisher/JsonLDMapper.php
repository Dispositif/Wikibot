<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Domain\Publisher;


use App\Domain\Publisher\Traits\AuthorConverterTrait;
use App\Domain\Publisher\Traits\MapperConverterTrait;
use App\Domain\Publisher\Traits\OpenAccessTrait;

class JsonLDMapper implements MapperInterface
{
    use MapperConverterTrait, AuthorConverterTrait, OpenAccessTrait;
    // public $log;

    public function process($jsonLD): array
    {
        return [
            'DATA-TYPE' => 'JSON-LD',
            'DATA-ARTICLE' => $jsonLD['@type'] === 'NewsArticle',

            'langue' => $this->convertLangue($jsonLD['language'] ?? $jsonLD['inLanguage'] ?? null),
            'périodique' => $this->clean($jsonLD['publisher']['name'] ?? null),
            'titre' => $this->clean($jsonLD['headline']), // obligatoire
            'url' => $jsonLD['url'] ?? $jsonLD['mainEntityOfPage']['@id'] ?? $jsonLD['mainEntityOfPage'] ?? null,
            'date' => $this->convertDate($jsonLD['datePublished'] ?? $jsonLD['dateCreated'] ?? null), //
            // 2020-03-19T19:13:01.000Z
            'auteur1' => $this->wikifyPressAgency(
                $this->cleanAuthor($this->clean($this->convertJsonLDAuthor($jsonLD, 0)))
            ),
            'auteur2' => $this->cleanAuthor($this->clean($this->convertJsonLDAuthor($jsonLD, 1))),
            'auteur3' => $this->cleanAuthor($this->clean($this->convertJsonLDAuthor($jsonLD, 2))),
            'auteur institutionnel' => $this->wikifyPressAgency(
                $this->cleanAuthor(
                    $this->clean($this->convertInstitutionnel($jsonLD))
                )
            ),
            //'éditeur' => $jsonLD['publisher']['name'] ?? null,
            'consulté le' => date('d-m-Y'),
            'accès url' => $this->convertURLaccess($jsonLD),
        ];
    }
}
