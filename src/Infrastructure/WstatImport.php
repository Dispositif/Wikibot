<?php

declare(strict_types=1);

namespace App\Infrastructure;

/**
 * Data import from https://wstat.fr (frwiki daily dump parsing).
 * https://wstat.fr/template/index.php?title=Ouvrage&query=inclusions&param=isbn&start=50000&limit=50&format=json
 * Class WstatImport.
 */
class WstatImport
{
    const MAX_IMPORT = 50000;

    private $params = [];

    private $max = 100;

    private $client;

    public function __construct(\GuzzleHttp\Client $client, ?array $params = null, ?int $max = 500)
    {
        $this->client = $client;
        $this->max = min(self::MAX_IMPORT, $max);

        //example
        if (!$params) {
            $params = [
                'title' => 'Ouvrage',
                'query' => 'inclusions',
                'param' => 'isbn',
                'start' => 50000,
                'limit' => 500,
            ];
        }
        $this->params = $params;
    }

    public function getUrl()
    {
        $this->params['format'] = 'json';

        return 'https://wstat.fr/template/index.php?'.http_build_query($this->params);
    }

    /**
     * @return array [ ['title' => ..., 'template' => ...] ]
     *
     * @throws \Exception
     */
    public function getData(): array
    {
        $data = [];
        $flag = true;
        while ($flag) {
            $json = $this->import($this->getUrl());
            $raw = json_decode($json, true);
            if (empty($raw)) {
                return [];
            }
            $data = array_merge($data, $this->parsingWstatData($raw));
            if ($this->max <= 0) {
                $flag = false;

                break;
            }

            // next page initialisation
            $this->params['start'] = (intval($this->params['start']) + $this->params['limit']);
            sleep(3);
        }

        return $data;
    }

    /**
     * Explode raw string.
     *
     * @param array $raw
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
            $this->max = ($this->max - 1);

            // validate and explode wstat data
            $pos = mb_strpos($line, '|', 0);
            if (!$pos || 0 === $pos) {
                continue;
            }
            $title = trim(mb_substr($line, 0, $pos));
            $template = trim(mb_substr($line, $pos + 1));
            $data[] = ['title' => $title, 'template' => $template];
        }

        return (array) $data;
    }

    /**
     * @param string $url
     *
     * @return string
     *
     * @throws \Exception
     */
    private function import(string $url)
    {
        $response = $this->client->get($url);
        if (200 !== $response->getStatusCode()) {
            throw new \Exception(
                sprintf(
                    'Error code: %s reason: %s',
                    $response->getStatusCode(),
                    $response->getReasonPhrase()
                )
            );
        }

        return $response->getBody()->getContents();
    }
}
