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

/**
 * todo Move Infra?
 * Class PublisherAction
 *
 * @package App\Application
 */
class PublisherAction
{
    private $url;

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * import source from URL with Guzzle.
     *todo DI Client
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
     * @param string $html
     *
     * @return array
     * @throws Exception
     */
    public function extractWebData(string $html): array
    {
        $ld = $this->extractLdJson($html);
        $meta = $this->getMetaTags($html);

        return ['JSON-LD' => $ld, 'meta' => $meta];
    }

    /**
     * extract LD-JSON metadata from <script type="application/ld+json">.
     *
     * @param string $html
     *
     * @return array
     * @throws Exception
     */
    private function extractLdJson(string $html): array
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
            if (!is_array($data)) {
                return [];
            }

            // filtrage : @type => BreadcrumbList (lemonde)
            // TODO : c'est quoi ça ?
            if (isset($data['@type']) && 'BreadcrumbList' === $data['@type']) {
                continue;
            }

            return $data;
        }

        return [];
    }

    /**
     * todo move/refac/delete?
     *
     * @param string $str
     *
     * @return array
     */
    private function getMetaTags(string $str): array
    {
        $pattern = '
  ~<\s*meta\s
  # using lookahead to capture type to $1
    (?=[^>]*?
    \b(?:name|property|http-equiv)\s*=\s*
    (?|"\s*([^"]*?)\s*"|\'\s*([^\']*?)\s*\'|
    ([^"\'>]*?)(?=\s*/?\s*>|\s\w+\s*=))
  )
  # capture content to $2
  [^>]*?\bcontent\s*=\s*
    (?|"\s*([^"]*?)\s*"|\'\s*([^\']*?)\s*\'|
    ([^"\'>]*?)(?=\s*/?\s*>|\s\w+\s*=))
  [^>]*>
  ~ix';

        if (preg_match_all($pattern, $str, $out)) {
            $combine = array_combine($out[1], $out[2]);

            return $combine ? $combine : [];
        }

        return [];
    }
}
