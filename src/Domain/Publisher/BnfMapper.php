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
 *
 * @package App\Domain\Publisher
 */
class BnfMapper extends AbstractBookMapper implements MapperInterface
{
    /**
     * @param $data array
     *
     * @return array
     */
    public function process($xml): array
    {
        /**
         * XML in Marc format
         *
         * @var $data SimpleXMLElement
         */
        return [
            'bnf' => $this->convertBnfIdent($xml),
            'isbn' => (string)$xml->xpath('//mxc:datafield[@tag="010"]/mxc:subfield[@code="a"]')[0],

            'titre' => (string)$xml->xpath('//mxc:datafield[@tag="200"]/mxc:subfield[@code="a"]')[0],
            'auteur1' => (string)$xml->xpath('//mxc:datafield[@tag="200"]/mxc:subfield[@code="f"]')[0],

            'lieu' => (string)$xml->xpath('//mxc:datafield[@tag="219"]/mxc:subfield[@code="a"]')[0],
            'éditeur' => (string)$xml->xpath('//mxc:datafield[@tag="219"]/mxc:subfield[@code="c"]')[0],
            //  <mxc:subfield code="d">DL 2017</mxc:subfield>
        ];
    }

    private function convertBnfIdent($xml): ?string
    {
        // ark:/12148/cb453986124
        $raw = (string)$xml->xpath('//srw:recordIdentifier[1]/text()')[0];

        if (preg_match('#ark:/[0-9]+/cb([0-9]+)#', $raw, $matches) > 0) {
            return (string)$matches[1];
        }
    }

}
