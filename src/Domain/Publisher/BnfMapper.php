<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe/Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Publisher;

use App\Domain\Enums\Language;
use SimpleXMLElement;

/**
 * Class BnfMapper
 * http://catalogue.bnf.fr/api/SRU?version=1.2&operation=searchRetrieve&query=bib.isbn%2Badj%2B%25222844940404%2522.
 */
class BnfMapper extends AbstractBookMapper implements MapperInterface
{
    private ?SimpleXMLElement $xml = null;

    /**
     * XML in UniMarc format.
     * See http://api.bnf.fr/formats-bibliographiques-intermarc-unimarc
     * https://www.transition-bibliographique.fr/systemes-et-donnees/manuel-unimarc-format-bibliographique/.
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

        // skip multi-records
        $nbResults = (int)$xml->xpath('//srw:numberOfRecords[1]')[0] ?? 0;
        if (1 !== $nbResults) {
            echo "BNF : $nbResults records (skip)\n";

            return [];
        }

        return [
            // Langue
            'langue' => $this->lang2wiki($this->xpath2string('//mxc:datafield[@tag="101"]/mxc:subfield[@code="a"][1]')),
            // c : Langue de l’œuvre originale
            'langue originale' => $this->stripLangFR(
                $this->lang2wiki(
                    $this->xpath2string('//mxc:datafield[@tag="101"]/mxc:subfield[@code="c"][1]')
                )
            ),
            // g : Langue du titre propre (si différent)
            'langue titre' => $this->stripLangFR(
                $this->lang2wiki(
                    $this->xpath2string('//mxc:datafield[@tag="101"]/mxc:subfield[@code="g"][1]')
                )
            ),
            /*
             * Bloc 200.
             * https://www.transition-bibliographique.fr/wp-content/uploads/2019/11/B200-2018.pdf
             */ // a : Titre propre
            'titre' => $this->xpath2string('//mxc:datafield[@tag="200"]/mxc:subfield[@code="a"][1]'),
            // d : Titre parralèle (autre langue)
            'titre original' => $this->xpath2string('//mxc:datafield[@tag="200"]/mxc:subfield[@code="d"][1]'),
            // e : Complément du titre
            'sous-titre' => $this->xpath2string('//mxc:datafield[@tag="200"]/mxc:subfield[@code="e"][1]', ', '),

            // Responsabilités : zone 200 trop merdique "Pierre Durand, Paul Dupond" ou "Paul Durand,..."
            'prénom1' => $this->xpath2string('//mxc:datafield[@tag="700"]/mxc:subfield[@code="b"]'),
            'nom1' => $this->xpath2string('//mxc:datafield[@tag="700"]/mxc:subfield[@code="a"]'),

            'prénom2' => $this->xpath2string('//mxc:datafield[@tag="701"]/mxc:subfield[@code="b"]'),
            'nom2' => $this->xpath2string('//mxc:datafield[@tag="701"]/mxc:subfield[@code="a"]'),

            // zone 200
            // h : Numéro de partie
            //            'volume' => $this->xpath2string('//mxc:datafield[@tag="200"]/mxc:subfield[@code="h"]'),
            // i : Titre de partie
            // v : numéro de volume
            'volume' => $this->xpath2string('//mxc:datafield[@tag="200"]/mxc:subfield[@code="v"][1]'),

            // 410 : collection
            'collection' => $this->xpath2string('//mxc:datafield[@tag="410"]/mxc:subfield[@code="a"][1]'),

            // Auteur : voir plutôt 7XX
            //  https://www.transition-bibliographique.fr/wp-content/uploads/2018/07/B7XX-6-2011.pdf

            // multi-zones
            'lieu' => $this->getLocation(),
            'éditeur' => $this->getPublisher(),
            'date' => $this->getPublishDate(),
            // 215
            'pages totales' => $this->convertPages(),

            //            'bnf' => $this->convertBnfIdent(), // pertinent si isbn ?
            'isbn2' => $this->xpath2string('//mxc:datafield[@tag="010"]/mxc:subfield[@code="a"][2]'),
            'isbn' => $this->extractISBN(),

            // hidden data
            'infos' => [
                'source' => 'BnF',
                'sourceTag' => $this->sourceTag(),
                'bnfAuteur1' => $this->xpath2string('//mxc:datafield[@tag="700"][1]/mxc:subfield[@code="3"][1]'),
                'ISNIAuteur1' => $this->formatISNI(
                    $this->xpath2string('//mxc:datafield[@tag="700"][1]/mxc:subfield[@code="o"][1]')
                ),
                'yearsAuteur1' => $this->xpath2string('//mxc:datafield[@tag="700"][1]/mxc:subfield[@code="f"][1]'),
            ],
        ];
    }

    private function xpath2string(string $path, ?string $glue = ', '): ?string
    {
        if ($glue === null) {
            $glue = ', ';
        }
        $elements = $this->xml->xpath($path);

        $res = [];
        foreach ($elements as $element) {
            if (isset($element) && $element instanceof SimpleXMLElement) {
                $res[] = (string)$element;
            }
        }

        if ($res !== []) {
            return implode($glue, $res);
        }

        return null;
    }

    private function extractISBN(): ?string
    {
        $isbn = $this->xpath2string('//mxc:datafield[@tag="010"]/mxc:subfield[@code="a"][1]') ?? '';

        // data pourrie fréquente :  "9789004232891, 9004232893"
        if (preg_match('#(\d{13})#', $isbn, $matches)) {
            return $matches[1];
        }
        if (preg_match('#(\d{10})#', $isbn, $matches)) {
            return $matches[1];
        }
        // ISBN avec tiret
        if (preg_match('#([0-9\-]{10,17})#', $isbn, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function formatISNI(?string $raw = null): ?string
    {
        if (!$raw) {
            return null;
        }
        if (preg_match('#^0000(000[0-4])(\d{4})(\d{3}[0-9X])$#', $raw, $matches) > 0) {
            return $raw;
        }
        // BnF curious format of ISNI
        if (preg_match('#^ISNI0000(000[0-4])(\d{4})(\d{3}[0-9X])$#', $raw, $matches) > 0) {
            return sprintf('0000 %s %s %s', $matches[1], $matches[2], $matches[3]);
        }

        return null;
    }

    /**
     * Strip FR lang
     *
     *
     */
    private function stripLangFR(?string $lang = null): ?string
    {
        return ('fr' !== $lang) ? $lang : null;
    }

    /**
     * Convert number of pages.
     * "1 vol. (126 p.)".
     */
    private function convertPages(): ?string
    {
        $raw = $this->xpath2string('//mxc:datafield[@tag="215"]/mxc:subfield[@code="a"]');
        if (!empty($raw) && preg_match('#(\d{2,}) p\.#', $raw, $matches) > 0) {
            return (string)$matches[1];
        }

        return null;
    }

    /**
     * todo gestion bilingue fr+en
     * ISO 639-1 http://www.loc.gov/standards/iso639-2/php/French_list.php.
     *
     *
     */
    private function lang2wiki(?string $lang = null): ?string
    {
        if (!empty($lang)) {
            return Language::iso2b2wiki($lang);
        }

        return null;
    }

    private function getPublisher(): ?string
    {
        // zone 210
        if ($tac = $this->xpath2string('//mxc:datafield[@tag="210"]/mxc:subfield[@code="c"]', ' / ')) {
            return $tac;
        }
        // 214 : nouvelle zone 2019
        if ($tac = $this->xpath2string('//mxc:datafield[@tag="214"]/mxc:subfield[@code="c"]', ' / ')) {
            return $tac;
        }

        // 219 ancienne zone ?
        if ($tac = $this->xpath2string('//mxc:datafield[@tag="219"]/mxc:subfield[@code="c"]', ' / ')) {
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
        if ($tac = $this->xpath2string('//mxc:datafield[@tag="214"]/mxc:subfield[@code="a"]', '/')) {
            return $tac;
        }
        // ancienne zone ?
        if ($tac = $this->xpath2string('//mxc:datafield[@tag="219"]/mxc:subfield[@code="a"]', '/')) {
            return $tac;
        }

        return null;
    }

    private function getPublishDate(): ?string
    {
        // zone 210 d : Date de publication, de diffusion, etc.
        if ($tac = $this->xpath2string('//mxc:datafield[@tag="210"]/mxc:subfield[@code="d"][1]')) {
            return $tac;
        }
        // 214 : nouvelle zone 2019
        if ($tac = $this->xpath2string('//mxc:datafield[@tag="214"]/mxc:subfield[@code="d"][1]')) {
            return $tac;
        }

        return null;
    }

    //    private function convertBnfIdent(): ?string
    //    {
    //        // ark:/12148/cb453986124
    //        $raw = $this->xpath2string('//srw:recordIdentifier[1]/text()');
    //
    //        if ($raw && preg_match('#ark:/[0-9]+/cb([0-9]+)#', $raw, $matches) > 0) {
    //            return (string)$matches[1];
    //        }
    //
    //        return null;
    //    }

    private function sourceTag(): ?string
    {
        $raw = $this->xpath2string('//srw:extraRecordData[1]/ixm:attr[@name="LastModificationDate"][1]');
        // 20190922
        if ($raw && preg_match('#^(\d{4})\d{4}$#', $raw, $matches) > 0) {
            return sprintf('BnF:%s', $matches[1]);
        }

        return null;
    }
}
