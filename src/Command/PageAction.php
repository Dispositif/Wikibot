<?php

namespace App\Command;

use Mediawiki\Api\ApiUser;
use Mediawiki\Api\MediawikiApi;
use Mediawiki\Api\MediawikiFactory;
use Mediawiki\DataModel\Page;

class PageAction
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
}
