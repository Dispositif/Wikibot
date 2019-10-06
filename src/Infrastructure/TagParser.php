<?php

namespace App\Infrastructure;

use DOMDocument;
use SimpleXMLElement;

class TagParser
{
    /*
     * @var SimpleXMLElement
     */
    protected $xml;

    public function __construct() { }

    /**
     * @param string $data
     *
     * @return $this
     */
    public function importHtml(string $data)
    {
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->strictErrorChecking = false;
        $doc->loadHTML(
            mb_convert_encoding($data, 'HTML-ENTITIES', 'UTF-8')
        );
        $this->xml = simplexml_import_dom($doc);

        return $this;
    }

    /**
     * Get the <ref> values
     * @return array
     * @throws \Exception
     */
    public function getRefValues(): array
    {
        $nodes = $this->xpathResults('//ref');

        $refs = [];
        // filter the empty <ref name="bla" />
        foreach ($nodes as $key => $node) {
            $raw = trim((string)$node);
            if (strlen($raw) > 1) {
                $refs[] = $raw;
            }
        }
        return $refs;
    }

    /**
     * @param string $path
     *
     * @return array string[]
     * @throws \Exception
     */
    public function xpathResults(string $path): array
    {
        if (!$this->xml instanceof SimpleXMLElement) {
            throw new \Exception('XML non défini');
        }
        $nodes = $this->xml->xpath($path); // SimpleXMLElement[]

        $results = [];
        // Note : empty value included
        foreach ($nodes as $key => $node) {
            $results[] = trim((string)$node);
        }

        return $results; // string[]
    }

}
