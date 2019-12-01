<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

declare(strict_types=1);

namespace App\Domain\Publisher;

use SimpleXMLElement;

/**
 * Class BnfMapper
 * http://catalogue.bnf.fr/api/SRU?version=1.2&operation=searchRetrieve&query=bib.isbn%2Badj%2B%25222844940404%2522
 *
 * @package App\Domain\Publisher
 */
class BnfMapper extends AbstractBookMapper implements MapperInterface
{
    /**
     * @var SimpleXMLElement
     */
    private $xml;

    /**
     * XML in UniMarc format.
     * See http://api.bnf.fr/formats-bibliographiques-intermarc-unimarc
     * https://www.transition-bibliographique.fr/systemes-et-donnees/manuel-unimarc-format-bibliographique/
     *
     * @param $xml
     *
     * @return array
     */
    public function process($xml): array
    {
        if (!$xml instanceof SimpleXMLElement) {
            return [];
        }
        $this->xml = $xml;


        return [
            'bnf' => $this->convertBnfIdent(),
            'isbn' => $this->xpath2string('//mxc:datafield[@tag="010"]/mxc:subfield[@code="a"][1]'),
            'isbn2' => $this->xpath2string('//mxc:datafield[@tag="010"]/mxc:subfield[@code="a"][2]'),

            // Langue
            'langue' => $this->lang2wiki($this->xpath2string('//mxc:datafield[@tag="101"]/mxc:subfield[@code="a"]')),
            // c : Langue de l’œuvre originale
            'langue originale' => $this->lang2wiki(
                $this->xpath2string('//mxc:datafield[@tag="101"]/mxc:subfield[@code="c"]')
            ),
            // g : Langue du titre propre (si différent)
            'langue titre' => $this->lang2wiki(
                $this->xpath2string('//mxc:datafield[@tag="101"]/mxc:subfield[@code="g"]')
            ),

            // Bloc 200
            // a : Titre propre
            'titre' => $this->xpath2string('//mxc:datafield[@tag="200"]/mxc:subfield[@code="a"][1]'),
            // e : Complément du titre
            'sous-titre' => $this->xpath2string('//mxc:datafield[@tag="200"]/mxc:subfield[@code="e"]'),
            // f : responsabilité principale "Pierre Durand, Paul Dupond" (XML de dingue pour ça...)
            'auteur1' => $this->xpath2string('//mxc:datafield[@tag="200"]/mxc:subfield[@code="f"]'),
            // g : Mention de responsabilité suivante
            'auteur2' => $this->xpath2string('//mxc:datafield[@tag="200"]/mxc:subfield[@code="g"]'),
            // h : Numéro de partie
            //            'volume' => $this->xpath2string('//mxc:datafield[@tag="200"]/mxc:subfield[@code="h"]'),
            // i : Titre de partie
            // v : numéro de volume
            'volume' => $this->xpath2string('//mxc:datafield[@tag="200"]/mxc:subfield[@code="v"]'),

            // 410 : collection
            'collection' => $this->xpath2string('//mxc:datafield[@tag="410"]/mxc:subfield[@code="a"]'),

            // Auteur : voir plutôt 7XX
            //  https://www.transition-bibliographique.fr/wp-content/uploads/2018/07/B7XX-6-2011.pdf

            // multi-zones
            'lieu' => $this->getLocation(),
            'éditeur' => $this->getPublisher(),
            'date' => $this->getPublishDate(),
            // 215
            'pages totales' => $this->convertPages(),
        ];
    }

    private function xpath2string(string $path, ?string $glue = ', '): ?string
    {
        $elements = $this->xml->xpath($path);

        $res = [];
        foreach ($elements as $element) {
            if (isset($element) && $element instanceof SimpleXMLElement) {
                $res[] = (string)$element;
            }
        }

        if (!empty($res)) {
            return implode($glue, $res);
        }

        return null;
    }

    /**
     * Convert number of pages.
     * "1 vol. (126 p.)"
     *
     * @return string|null
     */
    private function convertPages(): ?string
    {
        $raw = $this->xpath2string('//mxc:datafield[@tag="215"]/mxc:subfield[@code="a"]');
        if (!empty($raw) && preg_match('#([0-9]{2,}) p\.#', $raw, $matches) > 0) {
            return (string)$matches[1];
        }

        return null;
    }

    /**
     * todo refac and move.
     * ISO 639-1 http://www.loc.gov/standards/iso639-2/php/French_list.php
     *
     * @param string|null $lang
     *
     * @return string|null
     */
    private function lang2wiki(?string $lang = null): ?string
    {
        $iso2b_to_frlang = [];
        require __DIR__.'/../Enums/languageData.php';

        if (!empty($lang) && isset($iso2b_to_frlang[$lang])) {
            return $iso2b_to_frlang[$lang];
        }

        return null;
    }

    private function getPublisher(): ?string
    {
        // zone 210
        if ($tac = $this->xpath2string('//mxc:datafield[@tag="210"]/mxc:subfield[@code="c"]')) {
            return $tac;
        }
        // 214 : nouvelle zone 2019
        if ($tac = $this->xpath2string('//mxc:datafield[@tag="214"]/mxc:subfield[@code="c"]')) {
            return $tac;
        }

        // 219 ancienne zone ?
        if ($tac = $this->xpath2string('//mxc:datafield[@tag="219"]/mxc:subfield[@code="c"]')) {
            return $tac;
        }

        return null;
    }

    private function getLocation(): ?string
    {
        // zone 210
        if ($tac = $this->xpath2string('//mxc:datafield[@tag="210"]/mxc:subfield[@code="a"]', '/')) {
            return $tac;
        }
        // 214 : nouvelle zone 2019
        if ($tac = $this->xpath2string('//mxc:datafield[@tag="214"]/mxc:subfield[@code="a"]','/')) {
            return $tac;
        }
        // ancienne zone ?
        if ($tac = $this->xpath2string('//mxc:datafield[@tag="219"]/mxc:subfield[@code="a"]','/')) {
            return $tac;
        }

        return null;
    }

    private function getPublishDate(): ?string
    {
        // zone 210 d : Date de publication, de diffusion, etc.
        if ($tac = $this->xpath2string('//mxc:datafield[@tag="210"]/mxc:subfield[@code="d"]')) {
            return $tac;
        }
        // 214 : nouvelle zone 2019
        if ($tac = $this->xpath2string('//mxc:datafield[@tag="214"]/mxc:subfield[@code="d"]')) {
            return $tac;
        }

        return null;
    }

    private function convertBnfIdent(): ?string
    {
        // ark:/12148/cb453986124
        $raw = $this->xpath2string('//srw:recordIdentifier[1]/text()');

        if ($raw && preg_match('#ark:/[0-9]+/cb([0-9]+)#', $raw, $matches) > 0) {
            return (string)$matches[1];
        }

        return null;
    }

}
