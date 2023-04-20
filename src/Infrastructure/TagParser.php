<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\InfrastructurePorts\TagParserInterface;
use DOMDocument;
use Exception;
use SimpleXMLElement;

class TagParser implements TagParserInterface
{
    /** @var SimpleXMLElement|null */
    protected $xml;

    /**
     * import HTML string to SimpleXMLElement property.
     */
    public function importHtml(string $data): TagParserInterface
    {
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->strictErrorChecking = false;
        $doc->loadHTML(
            (string) mb_convert_encoding($data, 'HTML-ENTITIES', 'UTF-8')
        );
        $this->xml = simplexml_import_dom($doc);

        return $this;
    }

    /**
     * Get the <ref> values.
     *
     * @throws Exception
     */
    public function getRefValues(): array
    {
        $nodes = $this->xpathResults('//ref');

        $refs = [];
        // filter the empty <ref name="bla" />
        foreach ($nodes as $key => $node) {
            $raw = trim((string) $node);
            if (strlen($raw) > 1) {
                $refs[] = $raw;
            }
        }

        return $refs;
    }

    /**
     * @return array string[]
     *
     * @throws Exception
     */
    public function xpathResults(string $path): array
    {
        if (!$this->xml instanceof SimpleXMLElement) {
            throw new Exception('XML non défini');
        }
        $nodes = $this->xml->xpath($path); // SimpleXMLElement[]

        $results = [];
        // Note : empty value included
        foreach ($nodes as $key => $node) {
            $results[] = trim((string) $node);
        }

        return $results; // string[]
    }
}
