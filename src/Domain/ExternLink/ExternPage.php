<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Domain\ExternLink;

use App\Application\Http\ExternHttpClient;
use App\Domain\InfrastructurePorts\InternetDomainParserInterface;
use App\Domain\InfrastructurePorts\TagParserInterface;
use App\Domain\Utils\TextUtil;
use App\Infrastructure\InternetDomainParser;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Représente une page web d'un Lien Externe (hors wiki)
 * Class ExternPage
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

    /** @var TagParserInterface|null */
    private $tagParser;

    /** @var InternetDomainParserInterface|null */
    private $domainParser;

    /** @var LoggerInterface */
    private $log;

    /**
     * ExternPage constructor.
     * @throws Exception
     */
    public function __construct(
        string                         $url,
        string                         $html,
        ?TagParserInterface            $tagParser = null,
        ?InternetDomainParserInterface $domainParser = null,
        ?LoggerInterface               $log = null
    )
    {
        if (!ExternHttpClient::isHttpURL($url)) {
            throw new Exception('string is not an URL ' . $url);
        }
        $this->url = $url;
        $this->html = $html;
        $this->tagParser = $tagParser;
        $this->domainParser = $domainParser;
        $this->log = $log ?? new NullLogger();
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getData(): array
    {
        $ld = $this->parseLdJson($this->html);
        $meta = $this->parseMetaTags($this->html);

        $meta['html-lang'] = $this->parseHtmlLang($this->html); // <html lang="en">
        $meta['html-title'] = $this->parseHtmlTitle($this->html);
        $meta['html-h1'] = $this->parseHtmlFirstH1($this->html);
        $meta['html-url'] = $this->url;
        $meta['prettyDomainName'] = $this->getPrettyDomainName();
        $meta['robots'] = $this->getMetaRobotsContent($this->html);

        return ['JSON-LD' => $ld, 'meta' => $meta];
    }

    /**
     * extract LD-JSON metadata from <script type="application/ld+json">.
     * @throws Exception
     */
    private function parseLdJson(string $html): array
    {
        if (!$this->tagParser instanceof TagParserInterface) {
            return [];
        }

        $results = $this->tagParser->importHtml($html)->xpathResults(
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
     */
    public function getPrettyDomainName(): string
    {
        // Parse custom exceptions (free.fr, gouv.fr, etc)
        $rawDomain = InternetDomainParser::extractSubdomainString($this->url); //only php parsing
        foreach (self::PRETTY_DOMAIN_EXCLUSION as $end) {
            if (TextUtil::str_ends_with($rawDomain, $end)) {
                return $this->sanitizeSubDomain($rawDomain);
            }
        }

        // Parse using InternetDomainParser library
        return $this->sanitizeSubDomain($this->getRegistrableSubDomain() ?? $rawDomain); // use lib and cached data
    }

    /**
     * "http://www.bla.co.uk/fubar" => "bla.co.uk"
     * @throws Exception
     */
    public function getRegistrableSubDomain(): ?string
    {
        try {
            if (!ExternHttpClient::isHttpURL($this->url)) {
                throw new Exception('string is not an URL ' . $this->url);
            }
            if (!$this->domainParser instanceof InternetDomainParserInterface) {
                $this->log->notice('InternetDomainParser is not set');

                return null;
            }

            return $this->domainParser->getRegistrableDomainFromURL($this->url);
        } catch (Exception $e) {
            if ($this->log !== null) {
                $this->log->warning('InternetDomainParser->getRegistrableDomainFromURL NULL ' . $this->url);
            }
            throw new Exception('InternetDomainParser->getRegistrableDomainFromURL NULL', $e->getCode(), $e);
        }
    }

    /**
     * Extract language from <html lang="en-us"> tag.
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
     */
    private function parseHtmlTitle(string $html): ?string
    {
        if (preg_match('#<title>([^<]+)</title>#i', $html, $matches)) {
            return trim(strip_tags($matches[1]));
        }

        return null;
    }

    /**
     * Extract first <h1> from HTML.
     */
    private function parseHtmlFirstH1(string $html): ?string
    {
        if (preg_match('#<h1[^>]*>([^<]+)</h1>#i', $html, $matches)) {
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

    /**
     * Extract robots meta tag content.
     * <meta name="robots" content="noindex,noarchive">
     */
    private function getMetaRobotsContent(string $html): string
    {
        if (preg_match('#<meta[^>]+name="robots"[^>]+content="([^"]+)"#i', $html, $matches)) {
            return $matches[1];
        }

        return '';
    }
}
