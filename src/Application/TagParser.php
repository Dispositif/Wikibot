<?php

namespace App\Application;

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
     * @param string $path
     *
     * @return array
     * @throws \Exception
     */
    public function xpath(string $path): array
    {
        if (!$this->xml instanceof SimpleXMLElement) {
            throw new \Exception('XML non défini');
        }
        $nodes = $this->xml->xpath($path); // SimpleXMLElement[]

        $refs = [];

        // todo? extract in function xml2String() ?
        foreach ($nodes as $key => $node) {
            $raw = trim((string)$node);
            // filter the empty <ref name="bla" />
            if (strlen($raw) > 1) {
                $refs[] = $raw;
            }
        }

        return $refs;
    }

}
