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
            'isbn' => $this->xpath2string('//mxc:datafield[@tag="010"]/mxc:subfield[@code="a"]'),

            'titre' => (string)$xml->xpath('//mxc:datafield[@tag="200"]/mxc:subfield[@code="a"]')[0],

            // "Pierre Durand, Paul Dupond" (XML de dingue pour ça...)
            'auteur1' => $this->xpath2string('//mxc:datafield[@tag="200"]/mxc:subfield[@code="f"]'),

            'lieu' => $this->xpath2string('//mxc:datafield[@tag="219"]/mxc:subfield[@code="a"]'),
            'éditeur' => $this->xpath2string('//mxc:datafield[@tag="219"]/mxc:subfield[@code="c"]'),

            //  <mxc:subfield code="d">DL 2017</mxc:subfield>

            //            <mxc:datafield tag="101" ind1="0" ind2=" ">
            //<mxc:subfield code="a">fre</mxc:subfield>
            //</mxc:datafield>
            //<mxc:datafield tag="102" ind1=" " ind2=" ">
            //<mxc:subfield code="a">FR</mxc:subfield>

            //            <srw:extraRecordData>
            //<ixm:attr name="CreationDate">20010330</ixm:attr>
            //<ixm:attr name="LastModificationDate">20190919</ixm:attr>
            //<mn:score>7.336276</mn:score>
        ];
    }

    private function xpath2string(string $path): ?string
    {
        $element = $this->xml->xpath($path);
        if (isset($element[0]) && $element[0] instanceof SimpleXMLElement) {
            return (string)$element[0];
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
