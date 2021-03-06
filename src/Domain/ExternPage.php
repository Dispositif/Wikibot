<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019/2020 © Philippe/Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);


namespace App\Domain;

use App\Application\Http\ExternHttpClient;
use App\Domain\Utils\TextUtil;
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
    const PRETTY_DOMAIN_EXCLUSION
        = [
            '.中国',
            '.uk',
            '.jp',
            '.ma',
            '.kr',
            '.ca',
            '.name',
            '.gov',
            '.free.fr',
            '.gouv.fr',
            '.com.cn',
            'site.google.com',
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
        if (!ExternHttpClient::isWebURL($url)) {
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
            if (0 === strlen($json)) {
                continue;
            }
            $data = json_decode($json, true);
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

            return $combine ? $combine : [];
        }

        return [];
    }

    /**
     * test.com => test.com
     * bla.test.com => test.com
     * test.co.uk => test.co.uk (national commercial subdomain)
     * site.google.com => site.google.com (blog)
     *
     * @return string
     * @throws Exception
     */
    public function getPrettyDomainName(): string
    {
        $subDomain = $this->getSubDomain();

        foreach (self::PRETTY_DOMAIN_EXCLUSION as $end) {
            if (TextUtil::str_ends_with($subDomain, $end)) {
                return $subDomain;
            }
        }

        // bla.test.com => Test.com
        // Validate with "-" and unicode characters in domain name ?
        // todo test domain .中国 arabic, etc
        if (preg_match('#[^. /:]+\.\w+$#i', $subDomain, $matches)) {
            return $matches[0];
        }

        return $subDomain;
    }

    /**
     * "http://www.bla.co.uk/fubar" => "bla.co.uk"
     * @return string|null
     * @throws Exception
     */
    public function getSubDomain(): string
    {
        try {
            return ExternDomains::extractSubDomain($this->url);
        } catch (Exception $e) {
            if ($this->log) {
                $this->log->warning('ExternDomains::extractSubDomain NULL '.$this->url);
            }
            throw new Exception('ExternDomains::extractSubDomain NULL');
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
}
