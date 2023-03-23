<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Domain;

use App\Application\Http\ExternHttpClient;
use App\Domain\Utils\TextUtil;
use App\Infrastructure\InternetDomainParser;
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
    // todo move to config
    protected const PRETTY_DOMAIN_EXCLUSION
        = [
            '.中国',
            '.gov',
            '.free.fr',
            '.gouv.fr',
            '.com.cn',
            'site.google.com',
            'wordpress.com',
            'blogspot.com',
        ];

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
        if (!ExternHttpClient::isHttpURL($url)) {
            throw new Exception('string is not an URL '.$url);
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

        $meta['html-lang'] = $this->parseHtmlLang($this->html); // <html lang="en">
        $meta['html-title'] = $this->parseHtmlTitle($this->html);
        $meta['html-url'] = $this->url;

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
            if ($json === '') {
                continue;
            }
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data)
                || (isset($data['@type']) && is_string($data['@type']) && preg_match('#Breadcrumb#i', $data['@type']))
            ) {
                continue;
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

            return $combine ?: [];
        }

        return [];
    }

    /**
     * test.com => test.com
     * bla.test.com => test.com
     * test.co.uk => test.co.uk (national commercial subdomain)
     * site.google.com => site.google.com (blog)
     * bla.site.google.com => site.google.com (blog)
     *
     * @throws Exception
     */
    public function getPrettyDomainName(): string
    {
        // Parse custom exceptions (free.fr, gouv.fr, etc)
        $rawDomain = InternetDomainParser::extractSubdomainString($this->url);
        foreach (self::PRETTY_DOMAIN_EXCLUSION as $end) {
            if (TextUtil::str_ends_with($rawDomain, $end)) {
                return $this->sanitizeSubDomain($rawDomain);
            }
        }

        // Parse using InternetDomainParser library
        return $this->sanitizeSubDomain($this->getRegistrableSubDomain());
    }

    /**
     * "http://www.bla.co.uk/fubar" => "bla.co.uk"
     * @throws Exception
     */
    public function getRegistrableSubDomain(): string
    {
        try {
            if (!ExternHttpClient::isHttpURL($this->url)) {
                throw new \Exception('string is not an URL '.$this->url);
            }

            return InternetDomainParser::getRegistrableDomainFromURL($this->url);
        } catch (Exception $e) {
            if ($this->log !== null) {
                $this->log->warning('InternetDomainParser::getRegistrableDomainFromURL NULL '.$this->url);
            }
            throw new Exception('InternetDomainParser::getRegistrableDomainFromURL NULL', $e->getCode(), $e);
        }
    }

    /**
     * Extract language from <html lang="en-us"> tag.
     *
     * @param string $html
     *
     * @return string|null
     */
    private function parseHtmlLang(string $html): ?string
    {
        if (preg_match('#<html(?: [^>]+)? lang="([A-Z-]{2,15})"(?: [^>]+)?>#i', $html, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract webpage title from HTML <title>
     * not foolproof : example <!-- <title>bla</title> -->
     *
     * @param string $html
     *
     * @return string|null
     */
    private function parseHtmlTitle(string $html): ?string
    {
        if (preg_match('#<title>([^<]+)</title>#i', $html, $matches)) {
            return trim(strip_tags($matches[1]));
        }

        return null;
    }

    /**
     * TODO strip not unicode characters ?
     * TODO add initial capital letter ?
     * This method is used to sanitize subdomain name.
     * WTF ?!?!?!
     */
    protected function sanitizeSubDomain(string $subDomain): string
    {
        return str_replace('www.', '', $subDomain);
    }
}
