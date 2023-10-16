<?php
/*
 * This file is part of dispositif/wikibot application (@github)
 * 2019-2023 © Philippe M./Irønie  <dispositif@gmail.com>
 * For the full copyright and MIT license information, view the license file.
 */

declare(strict_types=1);

namespace App\Infrastructure;

use App\Application\InfrastructurePorts\HttpClientInterface;
use App\Domain\Utils\DateUtil;
use App\Infrastructure\Monitor\NullLogger;
use Psr\Log\LoggerInterface;

/**
 * Find the last addition edit (and date) of a string in a wiki article.
 * Request on http://wikipedia.ramselehof.de/wikiblame.php
 * See also https://github.com/FlominatorTM/wikiblame
 */
class WikiBlameAdapter
{
    protected HttpClientInterface $client;

    public function __construct(private readonly LoggerInterface|NullLogger $log = new NullLogger())
    {
        $this->client = ServiceFactory::getHttpClient();
    }

    /**
     * return [
     *    "versionid" => (int) 103659068
     *    "dateraw" => "09 mai 2014"
     *    "datetime" => DateTime
     */
    public function searchDiff(string $article, string $string, bool $hasWikicode = false): ?array
    {
        $url = 'http://wikipedia.ramselehof.de/wikiblame.php?project=wikipedia&article='
            . str_replace(' ', '+', $article)
            . '&needle=' . urlencode($string)
            . '&lang=fr&limit=2000&offjahr=2024&offmon=1&offtag=1&offhour=23&offmin=55'
            . '&searchmethod=int&order=desc'
            . '&force_wikitags=' . ($hasWikicode ? 'on' : 'off')
            . '&user_lang=fr&ignorefirst=0';

        $response = $this->client->get($url);

        if ($response->getStatusCode() !== 200) {
            $this->log->warning('WikiBlame: ' . $response->getStatusCode() . ' ' . $response->getReasonPhrase());
            return null;
        }

        $rawHtml = $response->getBody()->getContents();
        if (empty($rawHtml)) {
            $this->log->warning('WikiBlame: empty response');
            return null;
        }

        return $this->parseRawHtml($rawHtml);
    }

    private function parseRawHtml(string $rawHtml): ?array
    {
        if (!preg_match('#Insertion détectée entre le <a href="https:[^\"]+">[^<]+<\/a> et le <a href="https:[^\"]+&oldid=(\d+)">[\d:]+, ([^<]+)<\/a>: <a#', $rawHtml, $matches)) {
            $this->log->warning('WikiBlame: no result from HTML');
            return null;
        }

        return [
            'versionid' => (int)$matches[1],
            'dateraw' => $matches[2],
            'datetime' => DateUtil::simpleFrench2object($matches[2]),
        ];
    }
}