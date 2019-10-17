<?php

declare(strict_types=1);

namespace App\Application;

use App\Infrastructure\TagParser;
use Exception;
use GuzzleHttp\Client;

class PublisherAction
{
    private $url;

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    /**
     * import source from URL with Guzzle.
     *
     * @return string|null
     *
     * @throws Exception
     */
    public function getHTMLSource(): string
    {
        $client = new Client(
            [
                'timeout' => 5,
            ]
        );
        $response = $client->get($this->url);

        if (200 !== $response->getStatusCode()) {
            throw new Exception('response error '.$response);
        }
        $html = $response->getBody()->getContents();

        return $html;
    }

    /**
     * extract LD-JSON metadata from <script type="application/ld+json">.
     *
     * @param string $html
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function extractLdJson(string $html)
    {
        $parser = new TagParser();
        $results = $parser->importHtml($html)->xpathResults(
            '//script[@type="application/ld+json"]'
        );

        foreach ($results as $result) {
            $json = trim($result);
            // filtrage empty value (todo?)
            if (0 === strlen($json)) {
                continue;
            }
            $data = json_decode($json, true);

            // filtrage : @type => BreadcrumbList (lemonde)
            if ('BreadcrumbList' === $data['@type']) {
                continue;
            }

            return $data;
        }

        throw new Exception('no results');
    }
}
