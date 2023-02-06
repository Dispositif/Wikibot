<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe/Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Domain\Publisher;

use App\Domain\Utils\ArrayProcessTrait;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * Generic mapper for press/revue article on web.
 * Using JSON-LD and meta tags to obtain {article} data.
 * Generic mapper for web pages URL to wiki-template references.
 * Converting to {article}, {lien web} or {lien brisé}
 * Using JSON-LD, Open Graph and Dublin Core meta extracted from HTML.
 * Class ExternMapper
 *
 * @package App\Domain\Publisher
 */
class ExternMapper implements MapperInterface
{
    use ArrayProcessTrait, ExternConverterTrait;

    /**
     * @var LoggerInterface
     */
    private $log;
    /**
     * @var array
     */
    private $options = [];

    public function __construct(LoggerInterface $log, ?array $options=[])
    {
        $this->log = $log;
        $this->options = $options;
    }

    public function process($data): array
    {
        $dat = $this->processMapping($data);

        return ($dat === []) ? [] : $this->postProcess($dat);
    }

    /**
     * @param $data
     *
     * @return array
     * @throws Exception
     */
    protected function processMapping($data): array
    {
        $mapJson = [];
        $mapMeta = [];
        if (!empty($data['JSON-LD'])) {
            $mapJson = $this->processJsonLDMapping($data['JSON-LD']);
        }
        if (!empty($data['meta'])) {
            $mapMeta = (new OpenGraphMapper($this->options))->process($data['meta']);
        }

        // langue absente JSON-LD mais array_merge risqué (doublon)
        if ($mapJson !== []) {
            if (!isset($mapJson['langue']) && isset($mapMeta['langue'])) {
                $mapJson['langue'] = $mapMeta['langue'];
                $mapJson['DATA-TYPE'] = 'JSON-LD+META';
            }
            // récupère "accès url" de OpenGraph (prévaut sur JSON:'isAccessibleForFree'
            if(isset($mapMeta['accès url']) ){
                $mapJson['accès url'] = $mapMeta['accès url'];
                $mapJson['DATA-TYPE'] = 'JSON-LD+META';
            }

            return $mapJson;
        }

        return $mapMeta;
    }

    /**
     * todo move to mapper ?
     *
     * @param array $LDdata
     *
     * @return array
     */
    private function processJsonLDMapping(array $LDdata): array
    {
        if ($this->checkJSONLD($LDdata)) {
            return (new JsonLDMapper())->process($LDdata);
        }
        // gestion des multiples objets comme Figaro
        foreach ($LDdata as $dat) {
            if (is_array($dat) && $this->checkJSONLD($dat)) {
                return (new JsonLDMapper())->process($dat);
            }
        }

        return [];
    }

    protected function checkJSONLD(array $jsonLD): bool
    {
        return isset($jsonLD['headline']) && isset($jsonLD['@type']);
    }

    /**
     * todo Refac/move domain special mapping
     * todo Config parameter for post-process
     *
     * @param array $dat
     *
     * @return array
     */
    protected function postProcess(array $dat): array
    {
        $dat = $this->deleteEmptyValueArray($dat);
        if (isset($dat['langue']) && 'fr' === $dat['langue']) {
            unset($dat['langue']);
        }

        // Ça m'énerve ! Gallica met "vidéo" pour livre numérisé
        if (isset($dat['site']) && $dat['site'] === 'Gallica') {
            unset($dat['format']);
        }

        return $dat;
    }
}
