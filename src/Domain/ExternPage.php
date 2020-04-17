<?php
/**
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe M. <dispositif@gmail.com>
 * For the full copyright and MIT license information, please view the license file.
 */

declare(strict_types=1);


namespace App\Domain;

use App\Application\Http\ExternHttpClient;
use App\Infrastructure\TagParser;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * Représente une page web d'un Lien Externe (hors wiki)
 * Class ExternPage
 *
 * @package App\Domain
 */
class ExternPage
{
    /**
     * @var string
     */
    private $url;

    /**
     * @var string
     */
    private $html;

    /**
     * @var LoggerInterface|null
     */
    private $log;

    /**
     * ExternPage constructor.
     *
     * @param string               $url
     * @param string               $html
     * @param LoggerInterface|null $log
     *
     * @throws Exception
     */
    public function __construct(string $url, string $html, ?LoggerInterface $log = null)
    {
        if (!ExternHttpClient::isWebURL($url)) {
            throw new Exception('string is not an URL');
        }
        $this->url = $url;
        $this->html = $html;
        $this->log = $log;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getData(): array
    {
        $ld = $this->parseLdJson($this->html);
        $meta = $this->parseMetaTags($this->html);

        return ['JSON-LD' => $ld, 'meta' => $meta];
    }

    /**
     * extract LD-JSON metadata from <script type="application/ld+json">.
     *
     * @param string $html
     *
     * @return array
     * @throws Exception
     * @throws Exception
     */
    private function parseLdJson(string $html): array
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

            return $data;
        }

        return [];
    }

    /**
     * todo move? /refac/delete?
     *
     * @param string $str
     *
     * @return array
     */
    private function parseMetaTags(string $str): array
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

    /**
     * test.com => Test.com
     * bla.test.com => Test.com
     * test.co.uk => google.co.uk (national commercial subdomain)
     * site.google.com => site.google.com ;)
     *
     * @return string
     */
    public function getPrettyDomainName(): string
    {
        $subDomain = $this->getSubDomain();
        if (!strpos($subDomain, '.co.uk') && !strpos($subDomain, '.co.ma')
            && !strpos($subDomain, 'site.google.')
        ) {
            // bla.test.com => Test.com
            if (preg_match('#\w+\.\w+$#', $subDomain, $matches)) {
                return ucfirst($matches[0]);
            }
        }

        return $subDomain;
    }

    public function getSubDomain(): ?string
    {
        try {
            return ExternDomains::extractSubDomain($this->url);
        } catch (Exception $e) {
            if ($this->log) {
                $this->log->warning('ExternDomains::extractSubDomain NULL');
            }

            return null;
        }
    }

}
