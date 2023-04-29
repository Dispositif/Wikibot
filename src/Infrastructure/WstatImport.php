<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Application\InfrastructurePorts\PageListForAppInterface;
use App\Domain\InfrastructurePorts\PageListInterface;
use Exception;
use GuzzleHttp\Client;

/**
 * @unused
 * Data import from https://wstat.fr (frwiki daily dump parsing).
 * https://wstat.fr/template/index.php?title=Ouvrage&query=inclusions&param=isbn&start=50000&limit=50&format=json
 * Class WstatImport.
 */
class WstatImport implements PageListInterface, PageListForAppInterface
{
    public const MAX_IMPORT = 50000;

    private array $params = [];

    private ?int $max = 100;

    public function __construct(private readonly Client $client, ?array $params = null, ?int $max = 500)
    {
        $this->max = min(self::MAX_IMPORT, $max);

        //example
        // "nom de page" : https://wstat.fr/template/index.php?title=Ouvrage&query=inclusions-title&start=105000&limit=5000
        // "modèle complet" : https://wstat.fr/template/index.php?title=Ouvrage&query=inclusions&start=105000&limit=5000
        if (!$params) {
            $params = [
                'title' => 'Ouvrage',
                'query' => 'inclusions-title',
                //                'param' => 'isbn',
                'start' => 50000,
                'limit' => 5000,
            ];
        }
        $this->params = $params;
    }

    public function getUrl(): string
    {
        $this->params['format'] = 'json';
        // todo verify http_build_query() enc_type parameter
        return 'https://wstat.fr/template/index.php?'.http_build_query($this->params);
    }

    /**
     * @return array [ ['title' => ..., 'template' => ...] ]
     * @throws Exception
     */
    public function getData(): array
    {
        $data = [];
        while (true) {
            $json = $this->import($this->getUrl());
            $raw = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (empty($raw)) {
                return [];
            }
            $data = array_merge($data, $this->parsingWstatData($raw));
            echo count($data)." titles\n";
            if ($this->max <= 0) {
                break;
            }

            // next page initialisation
            $this->params['start'] = ((int) $this->params['start'] + $this->params['limit']);
            sleep(3);
        }

        return $data;
    }

    /**
     * Explode raw string.
     *
     *
     * @return array [['title' => ..., 'template' => ...]]
     */
    private function parsingWstatData(array $raw): array
    {
        // Generator ?
        // Alexandre S. Giffard|{{Ouvrage|langue=|auteur1=|prénom...
        $data = [];
        foreach ($raw as $line) {
            // end of page ?
            if ('<!-- + -->' === $line) {
                continue;
            }
            $this->max -= 1;

            // validate and explode wstat data
            $pos = mb_strpos((string) $line, '|', 0);
            if (false === $pos || 0 === $pos) {
                continue;
            }
            $title = trim(mb_substr((string) $line, 0, $pos));
            $template = trim(mb_substr((string) $line, $pos + 1));
            $data[] = ['title' => $title, 'template' => $template];
        }

        return $data;
    }

    /**
     *
     * @return string
     * @throws Exception
     */
    private function import(string $url)
    {
        $response = $this->client->get($url);
        if (200 !== $response->getStatusCode()) {
            throw new Exception(
                sprintf('Error code: %s reason: %s', $response->getStatusCode(), $response->getReasonPhrase())
            );
        }

        return $response->getBody()->getContents();
    }

    public function getPageTitles(): array
    {
        // TODO: Implement getPageTitles() method.
        return [];
    }
}
