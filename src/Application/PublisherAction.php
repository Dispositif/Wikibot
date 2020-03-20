<?php
/**
 * This file is part of dispositif/wikibot application
 * 2019 : Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the LICENSE file.
 */

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
     * @throws Exception
     */
    public function getHTMLSource(): string
    {
        $client = new Client(
            [
                'timeout' => 5,
                'headers' => ['User-Agent' => getenv('USER_AGENT')],
            ]
        );
        $response = $client->get($this->url);

        if (200 !== $response->getStatusCode()) {
            throw new Exception('response error '.$response->getStatusCode().' '.$response->getReasonPhrase());
        }

        return $response->getBody()->getContents();
    }

    /**
     * extract LD-JSON metadata from <script type="application/ld+json">.
     *
     * @param string $html
     *
     * @return mixed
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
            // TODO : c'est quoi ça ?
            if (isset($data['@type']) && 'BreadcrumbList' === $data['@type']) {
                continue;
            }

            return $data;
        }

        throw new Exception('extract LD-JSON no results');
    }
}
