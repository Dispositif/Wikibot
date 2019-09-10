<?php

namespace App\Application;

use App\Infrastructure\HtmlTagParser;
use Mediawiki\Api\ApiUser;
use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\MediawikiFactory;
use Mediawiki\DataModel\Page;

class WikiPageAction
{
    /**
     * @var MediawikiFactory
     */
    protected $services;
    /**
     * @var Page
     */
    protected $page;

    /**
     * PageAction constructor.
     *
     * @param string $title
     */
    public function __construct(string $title)
    {
        $this->apiConnect();

        $this->page = $this->services->newPageGetter()->getFromTitle($title);
    }

    protected function apiConnect(): void
    {
        $api = new MediawikiApi($_ENV['API_URL']);

        $api->login(
            new ApiUser($_ENV['API_USERNAME'], $_ENV['API_PASSWORD'])
        );

        $this->services = new MediawikiFactory($api);
    }

    /**
     * @return string|null
     */
    public function getText(): ?string
    {
        return $this->page->getRevisions()->getLatest()->getContent()->getData(
        );
    }

    /**
     * Extract <ref> data from text
     *
     * @param $text string
     *
     * @return array
     * @throws \Exception
     */
    public function extractRefFromText(string $text): ?array
    {
        $parser = new HtmlTagParser();
        $refs = $parser->importHtml($text)->xpath('//ref'); // []

        return (array)$refs;
    }

    /**
     * TODO $url parameter
     * check if any ref contains a targeted website/URL
     *
     * @param array $refs
     *
     * @return array
     */
    public function filterRefByURL(array $refs): array
    {
        $valid_ref = [];
        foreach ($refs as $ref) {
            if (preg_match(
                    '#(?<url>https?:\/\/(?:www\.)?lemonde\.fr\/[^ \]]+)#i',
                    $ref,
                    $matches
                ) > 0
            ) {
                $valid_ref[] = ['url' => $matches['url'], 'raw' => $ref];
            }
        }

        return $valid_ref;
    }

}
